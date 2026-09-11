<?php

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('SETTING-01: updates system settings when authorized', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);

    $this->post(route('settings.system.update'), [
        'locale_default' => 'id',
        'registration_enabled' => true,
        'license_mode' => 'global',
    ])->assertRedirect();

    expect(Setting::get('locale_default'))->toBe('id');
    expect((bool) Setting::get('registration_enabled'))->toBeTrue();
});

it('SETTING-02: denies system settings access without feature.manage', function () {
    // ponytail: no role = no feature.manage permission -> 403
    $user = User::where('email', 'admin@laravel-base.local')->first();
    $user->removeRole('super-admin');
    $this->actingAs($user);
    $this->get(route('settings.system'))->assertForbidden();
});

it('SETTING-04: register route is fail-closed when registration disabled', function () {
    Setting::set('registration_enabled', false);
    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [])->assertNotFound();
});

it('SETTING-05: register validation rejects weak passwords and duplicates', function () {
    Setting::set('registration_enabled', true);

    // weak password (missing caps/symbol/number -> fails regex + min)
    $this->post(route('register.store'), [
        'name' => 'Weak', 'username' => 'weak'.time(),
        'email' => 'weak'.time().'@example.com',
        'password' => 'short', 'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    // duplicate username
    $this->post(route('register.store'), [
        'name' => 'Dup', 'username' => 'superadmin',
        'email' => 'dup'.time().'@example.com',
        'password' => 'StrongP@ssw0rd123', 'password_confirmation' => 'StrongP@ssw0rd123',
    ])->assertSessionHasErrors('username');
});
