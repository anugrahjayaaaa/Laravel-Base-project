<?php

use App\Models\Role;
use App\Models\User;
use Laravel\Pennant\Feature;

beforeEach(function () {
    $this->seed();
});

/*
 * Create a user with exactly the given permissions (no role dependency).
 * Mirrors makeUserWith in FeatureFlagTest without redeclaring the symbol.
 */
function userWithPerms(array $perms): User
{
    return User::create([
        'name' => 'Tmp', 'username' => 'tmp_'.uniqid(), 'phone' => '+628****0000',
        'email' => uniqid().'@example.com', 'password' => bcrypt('x'), 'email_verified_at' => now(),
    ])->syncPermissions($perms);
}

it('shows Access Management parent when user has one child permission', function () {
    $html = $this->sidebarHtmlFor(userWithPerms(['user.view']));

    expect($html)->toContain(ui('access_management'))
        ->and($html)->toContain(route('users.index'))
        ->and($html)->not->toContain(route('roles.index'))
        ->and($html)->not->toContain(route('permissions.index'));
});

it('shows Access Management parent with only authorized children (multiple perms)', function () {
    $html = $this->sidebarHtmlFor(userWithPerms(['user.view', 'permission.view']));

    expect($html)->toContain(ui('access_management'))
        ->and($html)->toContain(route('users.index'))
        ->and($html)->toContain(route('permissions.index'))
        ->and($html)->not->toContain(route('roles.index'));
});

it('hides Access Management parent when user has zero child permissions', function () {
    $html = $this->sidebarHtmlFor(userWithPerms([]));

    expect($html)->not->toContain(ui('access_management'))
        ->and($html)->not->toContain(route('users.index'))
        ->and($html)->not->toContain(route('roles.index'))
        ->and($html)->not->toContain(route('permissions.index'));
});

it('shows Monitoring parent when user has one child permission', function () {
    $html = $this->sidebarHtmlFor(userWithPerms(['audit.view']));

    expect($html)->toContain(ui('monitoring'))
        ->and($html)->toContain(route('audit.index'))
        ->and($html)->not->toContain(route('logs.index'))
        ->and($html)->not->toContain(url('/telescope'));
});

it('shows Monitoring parent with only authorized children (multiple perms)', function () {
    $html = $this->sidebarHtmlFor(userWithPerms(['audit.view', 'periscope.view']));

    expect($html)->toContain(ui('monitoring'))
        ->and($html)->toContain(route('audit.index'))
        ->and($html)->toContain(url('/periscope'))
        ->and($html)->not->toContain(route('logs.index'))
        ->and($html)->not->toContain(url('/telescope'));
});

it('hides Monitoring parent when user has zero child permissions', function () {
    $html = $this->sidebarHtmlFor(userWithPerms(['user.view'])); // unrelated

    expect($html)->not->toContain(ui('monitoring'))
        ->and($html)->not->toContain(route('audit.index'))
        ->and($html)->not->toContain(route('logs.index'))
        ->and($html)->not->toContain(url('/telescope'))
        ->and($html)->not->toContain(url('/periscope'));
});

it('shows all Access Management and Monitoring children to super-admin', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $html = $this->sidebarHtmlFor($admin);

    expect($html)->toContain(ui('access_management'))
        ->and($html)->toContain(ui('monitoring'))
        ->and($html)->toContain(route('users.index'))
        ->and($html)->toContain(route('roles.index'))
        ->and($html)->toContain(route('permissions.index'))
        ->and($html)->toContain(route('audit.index'))
        ->and($html)->toContain(route('logs.index'));
});

it('hides Access Management parent when feature flag is off', function () {
    Feature::deactivate('users');
    $html = $this->sidebarHtmlFor(userWithPerms(['user.view']));

    expect($html)->not->toContain(ui('access_management'));
    Feature::activate('users');
});

it('retains route-level authorization for child routes', function () {
    $user = userWithPerms([]);
    $this->actingAs($user)->get(route('users.index'))->assertForbidden(); // 403, not 404
    $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
});
