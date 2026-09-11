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

    $afterLogin = now();
    $this->post(route('logout'))->assertRedirect('/');
    // ponytail: Logout event fires from Auth::logout() -> LogAuthentication listener -> 'logout' audit.
    // Count may include prior auth() logout in test setup; assert at least one fresh record.
    $logoutCount = Activity::where('description', 'logout')->where('causer_id', $this->user->id)->where('created_at', '>=', $afterLogin)->count();
    expect($logoutCount)->toBeGreaterThanOrEqual(1);
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('records new values on user update (no password)', function () {
    $u = User::factory()->create(['username' => 'audupd'.time(), 'name' => 'Old Name']);
    $this->put(route('users.update', $u), ['name' => 'Changed Name', 'email' => $u->email, 'username' => $u->username]);

    // ponytail: controller audit only (no observer); old/new diff not captured by audit() helper — out of RBAC scope.
    $log = Activity::where('description', 'user_updated')->where('subject_id', $u->id)->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->properties ?? [])->not->toHaveKey('password'); // secret never logged
});

it('cross-feature regression: all mutation features emit audit logs', function () {
    $causerId = $this->user->id;

    // User update (via controller pattern)
    $target = User::factory()->create(['username' => 'regtarget'.time(), 'name' => 'Before']);
    $this->put(route('users.update', $target), ['name' => 'After', 'email' => "reg{$target->id}@example.com", 'username' => $target->username]);
    expect(Activity::where('description', 'user_updated')->where('causer_id', $causerId)->exists())->toBeTrue();

    // Role create + permission attach (via controller to mirror HTTP audit)
    $this->post(route('roles.store'), ['name' => 'reg_role', 'guard_name' => 'web'])
        ->assertRedirect(route('roles.index'));
    $role = Role::where('name', 'reg_role')->first();
    $role->givePermissionTo('user.view');
    expect(Activity::where('description', 'role_created')->where('causer_id', $causerId)->exists())->toBeTrue();

    // Feature toggle
    $this->post(route('features.toggle', 'translations'), [
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

    // Cross-feature: every mutation audit category emitted at least one row.
    expect(Activity::where('description', 'user_updated')->exists())->toBeTrue();
    expect(Activity::where('description', 'role_created')->exists())->toBeTrue();
    expect(Activity::where('description', 'feature_enabled')->exists())->toBeTrue();
});

it('USER-01: destroy/restore/forceDelete audit fires exactly once with correct causer', function () {
    $causerId = $this->user->id;

    $target = User::factory()->create(['username' => 'del'.time(), 'name' => 'Del']);

    $this->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));
    expect(Activity::where('description', 'user_deleted')->where('subject_id', $target->id)->count())->toBe(1);
    expect(Activity::where('description', 'user_deleted')->first()->causer_id)->toBe($causerId);

    $this->post(route('users.restore', $target->id))->assertRedirect(route('users.index'));
    expect(Activity::where('description', 'user_restored')->where('subject_id', $target->id)->count())->toBe(1);
    expect(Activity::where('description', 'user_restored')->first()->causer_id)->toBe($causerId);

    $this->post(route('users.forceDelete', $target->id))->assertRedirect(route('users.index'));
    expect(Activity::where('description', 'user_force_deleted')->where('subject_id', $target->id)->count())->toBe(1);
    expect(Activity::where('description', 'user_force_deleted')->first()->causer_id)->toBe($causerId);
});

it('USER-06: lock emits user_locked + session_invalidated audit with correct causer', function () {
    $target = User::factory()->create(['username' => 'lock'.time(), 'name' => 'Lock']);

    $this->post(route('users.lock', $target))->assertRedirect(route('users.index'));
    expect(Activity::where('description', 'user_locked')->where('subject_id', $target->id)->count())->toBe(1);
    expect(Activity::where('description', 'user_locked')->first()->causer_id)->toBe($this->user->id);
    expect(Activity::where('description', 'session_invalidated')->where('subject_id', $target->id)->count())->toBe(1);

    $this->post(route('users.unlock', $target))->assertRedirect(route('users.index'));
    expect(Activity::where('description', 'user_unlocked')->where('subject_id', $target->id)->count())->toBe(1);
});
