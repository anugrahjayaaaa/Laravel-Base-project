<?php

use App\Models\License;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    cache()->flush();
    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    Setting::set('license_mode', 'global');
    cache()->flush();
    Feature::activate('users');
    Feature::activate('billing');
});

function makeUser(array $perms = [], string $roleName = 'sub'): User
{
    $role = Role::findOrCreate($roleName, 'web');
    $role->syncPermissions($perms);
    $user = User::create([
        'name' => 'Sub',
        'username' => 'sub_'.uniqid(),
        'email' => uniqid().'@example.com',
        'phone' => '+628'.uniqid(),
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('superadmin can access enabled module routes', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);

    $this->get(route('users.index'))->assertOk();
    $this->get(route('roles.index'))->assertOk();
});

it('superadmin receives 404 when pennant feature is disabled', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);

    Feature::deactivate('users');
    $this->get(route('users.index'))->assertNotFound();

    Feature::deactivate('roles');
    $this->get(route('roles.index'))->assertNotFound();

    Feature::activate('users');
    Feature::activate('roles');
});

it('per_user checkout activates only the purchasing user license', function () {
    config(['billing.fake' => true]);

    Setting::set('license_mode', 'per_user');
    cache()->flush();

    Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 99000,
        'is_active' => true,
        'billing_period' => 'monthly',
        'limits' => ['max_members' => 5],
        'features' => ['api-tokens'],
    ]);

    $userA = makeUser(['billing.cancel'], 'sub_a');
    $userB = makeUser(['billing.cancel'], 'sub_b');

    // Seed B with an active license before A checks out
    $bKey = LicenseService::issueFor($userB, 'pro', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($bKey, null, $userB);

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();

    $this->actingAs($userA);
    $this->post(route('billing.checkout'), ['plan_slug' => 'pro'])
        ->assertRedirect(route('billing.index'));

    $userA->refresh();
    $userB->refresh();

    expect($userA->license()->first()?->plan_slug)->toBe('pro');
    expect($userB->license()->first()?->plan_slug)->toBe('pro');

    $globalKey = Setting::get('license_key');
    $globalPlan = Setting::get('active_plan');

    expect($globalKey)->toBeNull();
    expect($globalPlan)->toBe('free');
});

it('per_user dashboard warning uses only the authenticated users license', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $admin = User::where('email', 'admin@laravel-base.local')->first();

    $userA = makeUser(['audit.view'], 'user_a');
    LicenseService::issueFor($userA, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(3)]);

    $userB = makeUser(['audit.view'], 'user_b');
    LicenseService::issueFor($userB, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(10)]);

    cache()->flush();

    $this->actingAs($userA)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('3 days')
        ->assertDontSee('10 days');

    $this->actingAs($userB)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('10 days')
        ->assertDontSee('3 days');
});

it('normal user is denied when plan disallows an otherwise granted permission', function () {
    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();

    $user = makeUser(['user.view'], 'plan_bounded');
    $this->actingAs($user);

    $this->get(route('users.index'))->assertForbidden();
});

it('pennant 404s for normal users and superadmin when disabled', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $user = makeUser(['user.view'], 'normal');

    Feature::deactivate('users');

    $this->actingAs($admin)->get(route('users.index'))->assertNotFound();
    $this->actingAs($user)->get(route('users.index'))->assertNotFound();

    Feature::activate('users');
});

it('sidebar hides disabled modules for normal users when pennant is off', function () {
    $user = makeUser(['user.view'], 'sidebar_user');

    Feature::deactivate('users');
    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('users.index'));

    Feature::activate('users');
});
