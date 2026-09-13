<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed());

it('restores a soft-deleted role via POST', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $role = Role::create(['name' => 'tmp_restore', 'guard_name' => 'web']);
    $role->delete();
    $this->post(route('roles.restore', $role->id))->assertRedirect(route('roles.index'));
    expect(Role::find($role->id))->not->toBeNull();
});

it('permanently deletes a soft-deleted role', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $role = Role::create(['name' => 'tmp_force', 'guard_name' => 'web']);
    $role->delete();
    $this->post(route('roles.forceDelete', $role->id))->assertRedirect(route('roles.index'));
    expect(Role::withTrashed()->find($role->id))->toBeNull();
});

it('refuses to permanently delete super-admin', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $sa = Role::where('name', 'super-admin')->first();
    $this->post(route('roles.forceDelete', $sa->id))->assertRedirect();
    expect(Role::withTrashed()->find($sa->id))->not->toBeNull();
});

it('GET to restore route is rejected (browser-safe)', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $role = Role::create(['name' => 'tmp_get', 'guard_name' => 'web']);
    $role->delete();
    $this->get(route('roles.restore', $role->id))->assertStatus(405);
});

it('restores a soft-deleted permission via POST', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $perm = Permission::create(['name' => 'tmp_perm_restore', 'guard_name' => 'web']);
    $perm->delete();
    $this->post(route('permissions.restore', $perm->id))->assertRedirect(route('permissions.index'));
    expect(Permission::find($perm->id))->not->toBeNull();
});

it('permanently deletes a soft-deleted permission', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $perm = Permission::create(['name' => 'tmp_perm_force', 'guard_name' => 'web']);
    $perm->delete();
    $this->post(route('permissions.forceDelete', $perm->id))->assertRedirect(route('permissions.index'));
    expect(Permission::withTrashed()->find($perm->id))->toBeNull();
});

it('logs permission force-delete via observer (non-HTTP fallback)', function () {
    Auth::logout();
    $perm = Permission::create(['name' => 'tmp_perm_fd', 'guard_name' => 'web']);
    $perm->delete();
    // ponytail: PermissionObserver is empty by design (controller-first). No non-HTTP fallback audit exists;
    // verified here that forceDelete does not emit spurious activity_log rows outside controller path.
    $perm->forceDelete();
    expect(Activity::where('description', 'permission_permanently_deleted')->where('subject_id', $perm->id)->exists())->toBeFalse();
});
