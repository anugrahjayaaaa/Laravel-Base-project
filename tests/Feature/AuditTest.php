<?php

use App\Models\Role;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($this->user);
});

it('logs role creation via observer', function () {
    Role::create(['name' => 'audited_role', 'guard_name' => 'web']);
    expect(Activity::where('description', 'role_created')->exists())->toBeTrue();
});

it('shows audit index with logged activity', function () {
    Role::create(['name' => 'audited_role2', 'guard_name' => 'web']);
    $this->get(route('audit.index'))
        ->assertOk()
        ->assertSee('role_created');
});

it('filters audit by action', function () {
    Role::create(['name' => 'audited_role3', 'guard_name' => 'web']);
    $this->get(route('audit.index', ['action' => 'role_created']))
        ->assertOk()
        ->assertSee('role_created');
});

it('logs a real login event', function () {
    auth()->logout(); // ensure not already authenticated so attempt() fires Login
    $this->post(route('login.store'), [
        'identifier' => 'superadmin',
        'password' => '#Password123',
    ])->assertRedirect(route('dashboard'));

    expect(Activity::where('description', 'login_success')->exists())->toBeTrue();
});

it('login_success audit fires exactly once (no double-log from attempt + manual event)', function () {
    auth()->logout();
    $countBefore = Activity::where('description', 'login_success')->count();

    $this->post(route('login.store'), [
        'identifier' => 'superadmin',
        'password' => '#Password123',
    ])->assertRedirect(route('dashboard'));

    $countAfter = Activity::where('description', 'login_success')->count();
    expect($countAfter)->toBe($countBefore + 1);
});

it('logout fires logout audit and invalidates the session', function () {
    auth()->logout();
    $this->post(route('login.store'), [
        'identifier' => 'superadmin',
        'password' => '#Password123',
    ])->assertRedirect(route('dashboard'));

    expect(Activity::where('description', 'logout')->exists())->toBeFalse();
    $this->post(route('logout'))->assertRedirect('/');
    expect(Activity::where('description', 'logout')->exists())->toBeTrue();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('records old and new values on user update (no password)', function () {
    $u = User::factory()->create(['username' => 'audupd'.time()]);
    $oldName = $u->name;
    $u->update(['name' => 'Changed Name', 'password' => bcrypt('NewPass@12345')]);

    $log = Activity::where('description', 'user_updated')->where('subject_id', $u->id)->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->properties['old']['name'])->toBe($oldName);
    expect($log->properties['new']['name'])->toBe('Changed Name');
    expect($log->properties['new'])->not->toHaveKey('password'); // secret never logged
});

it('cross-feature regression: all mutation features emit audit logs', function () {
    $causerId = $this->user->id;

    // User update (via controller pattern)
    $target = User::factory()->create(['username' => 'regtarget'.time(), 'name' => 'Before']);
    $this->put(route('users.update', $target), ['name' => 'After', 'email' => "reg{$target->id}@example.com"]);
    expect(Activity::where('description', 'user_updated')->where('causer_id', $causerId)->exists())->toBeTrue();

    // Role create + permission attach (via controller to mirror HTTP audit)
    $this->post(route('roles.store'), ['name' => 'reg_role', 'guard_name' => 'web'])
        ->assertRedirect(route('roles.index'));
    $role = Role::where('name', 'reg_role')->first();
    $role->givePermissionTo('user.view');
    expect(Activity::where('description', 'role_created')->where('causer_id', $causerId)->exists())->toBeTrue();

    // Feature toggle
    $this->post(route('features.toggle', 'translation'), [
        'enabled' => true,
    ]);
    expect(Activity::where('description', 'feature_enabled')->where('causer_id', $causerId)->exists())->toBeTrue();

    // Login event
    auth()->logout();
    $this->post(route('login.store'), [
        'identifier' => 'superadmin',
        'password' => '#Password123',
    ])->assertRedirect(route('dashboard'));
    expect(Activity::where('description', 'login_success')->exists())->toBeTrue();

    // All logs must have non-null causer
    expect(Activity::whereNull('causer_id')->count())->toBe(0);
});
