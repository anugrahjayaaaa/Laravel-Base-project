<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
});

function makeUserWith(array $perms, bool $manager = false): User
{
    $role = Role::create(['name' => 'tmp_'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($perms);
    if ($manager) {
        $role->givePermissionTo('feature.manage');
    }
    $user = User::create([
        'name' => 'Tmp', 'username' => 'tmp_'.uniqid(), 'phone' => '+628****0000',
        'email' => uniqid().'@example.com', 'password' => bcrypt('x'), 'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('Feature::active() returns enabled state and fails closed when missing', function () {
    expect(Feature::active('users'))->toBeTrue();
    expect(Feature::active('does-not-exist'))->toBeFalse();
});

it('lists feature flags (manager only)', function () {
    $this->actingAs(makeUserWith([], true));
    $this->get(route('features.index'))->assertOk()->assertSee('Feature Flags');
});

it('toggles a feature off and back on', function () {
    $this->actingAs(makeUserWith([], true));
    $this->post(route('features.toggle', 'users'), ['enabled' => '0'])
        ->assertRedirect(route('features.index'));
    expect(Feature::active('users'))->toBeFalse();

    $this->post(route('features.toggle', 'users'), ['enabled' => '1'])
        ->assertRedirect(route('features.index'));
    expect(Feature::active('users'))->toBeTrue();
});

it('blocks a non-manager when feature is off, even with permission', function () {
    Feature::deactivate('users');
    $user = makeUserWith(['user.view']); // has permission, no feature.manage

    $this->actingAs($user)->get(route('users.index'))->assertNotFound();
});

it('lets a feature.manage holder bypass the off gate', function () {
    // ponytail: kill-switch — disabled flag blocks everyone, including managers.
    Feature::deactivate('users');
    $manager = makeUserWith(['user.view'], true);

    $this->actingAs($manager)->get(route('users.index'))->assertNotFound();
});

it('allows a module route when its feature is on', function () {
    Feature::activate('users');
    $user = makeUserWith(['user.view']);

    $this->actingAs($user)->get(route('users.index'))->assertOk();
});

it('hides a feature-off menu item from a non-manager sidebar', function () {
    Feature::deactivate('users');
    $user = makeUserWith(['user.view']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('/users');
});

it('shows a feature-off menu item to a feature.manage holder', function () {
    // ponytail: kill-switch — disabled flag hides the menu for everyone.
    Feature::deactivate('users');
    $manager = makeUserWith(['user.view'], true);

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('/users');
});

it('logs feature_enabled and feature_disabled on toggle', function () {
    $manager = makeUserWith([], true);
    $this->actingAs($manager);

    $this->post(route('features.toggle', 'users'), ['enabled' => '0'])
        ->assertRedirect(route('features.index'));
    expect(Activity::where('causer_id', $manager->id)
        ->where('description', 'feature_disabled')
        ->whereRaw('JSON_EXTRACT(properties, "$.feature") = ?', ['users'])->exists())->toBeTrue();

    $this->post(route('features.toggle', 'users'), ['enabled' => '1'])
        ->assertRedirect(route('features.index'));
    expect(Activity::where('causer_id', $manager->id)
        ->where('description', 'feature_enabled')
        ->whereRaw('JSON_EXTRACT(properties, "$.feature") = ?', ['users'])->exists())->toBeTrue();
});
