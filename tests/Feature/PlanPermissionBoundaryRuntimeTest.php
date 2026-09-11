<?php

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    // Clear settings cache — array cache persists in-process across tests
    cache()->flush();
    // Ensure fresh free plan state
    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
});

/**
 * CHALLENGE 3: Plan permission boundary tests.
 * Proves Plan acts as capability ceiling — Role + Plan both needed.
 * Permission changes MUST NOT happen on Plan change.
 *
 * NOTE: These tests use a NON-superadmin user with an explicit role to
 * properly test the Plan boundary. The seeded super-admin user bypasses
 * the Plan boundary via isSuperAdmin() — see ArchitectureAuditTest for
 * superadmin-specific tests.
 */
function activatePro(array $overrides = []): Plan
{
    $pro = Plan::where('slug', 'pro')->first();

    $pro->update(array_merge([
        'name' => 'Pro',
        'price_monthly' => 99000,
        'is_active' => true,
        'billing_period' => 'monthly',
        'limits' => ['allowed_permissions' => ['user.view']],
        'features' => ['users'],
    ], $overrides));

    $key = LicenseService::issue('pro', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key);

    return $pro;
}

/**
 * Helper: create a non-superadmin user with a specific role + permissions.
 */
function makeBoundedUser(array $perms = [], string $roleName = 'bounded'): User
{
    $role = Role::findOrCreate($roleName, 'web');
    $role->syncPermissions($perms);
    $user = User::create([
        'name' => 'Bounded',
        'username' => 'bounded_'.uniqid(),
        'email' => 'bounded_'.uniqid().'@example.com',
        'phone' => '+628****0000',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('Scenario A — role has permission AND plan allows → access granted', function () {
    activatePro(['limits' => ['allowed_permissions' => ['user.view']]]);

    $user = makeBoundedUser(['user.view'], 'bounded');
    $this->actingAs($user);

    expect($user->can('user.view'))->toBeTrue();
    $this->get(route('users.index'))->assertOk();
});

it('Scenario B — role has permission but plan denies → access denied', function () {
    // Free plan: allowed_permissions is [] → plan denies everything
    $user = makeBoundedUser(['user.view'], 'bounded');
    $this->actingAs($user);

    expect(PlanService::for($user)->allows('user.view'))->toBeFalse();
    expect($user->can('user.view'))->toBeFalse();  // Plan boundary blocks

    // Role still has the permission — unchanged
    $role = $user->roles->first();
    expect($role->hasPermissionTo('user.view'))->toBeTrue();

    // No model_has_permissions written
    $direct = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count();
    expect($direct)->toBe(0);
});

it('Scenario C — plan allows permission but role does not have it → access denied', function () {
    $viewer = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
    // viewer does NOT get user.view

    activatePro(['limits' => ['allowed_permissions' => ['user.view']]]);

    $user = makeBoundedUser([], 'viewer_bounded');
    $this->actingAs($user);

    // Plan allows but role doesn't have it → still denied
    expect(PlanService::for($user)->allows('user.view'))->toBeTrue();
    expect($user->can('user.view'))->toBeFalse(); // role doesn't have it
});

it('Scenario D — plan downgrade: role keeps permission, access denied, no DB mutation', function () {
    // Use a role name that makeBoundedUser won't reset
    $role = Role::findOrCreate('admin_bounded', 'web');
    $role->givePermissionTo('user.view');

    // Start: pro plan allows user.view
    activatePro(['limits' => ['allowed_permissions' => ['user.view']]]);

    // Create user with the admin_bounded role (makeBoundedUser resets perms on the role,
    // so assign role AFTER giving permissions)
    $user = User::create([
        'name' => 'Bounded', 'username' => 'bounded_'.uniqid(),
        'email' => 'bounded_'.uniqid().'@example.com',
        'phone' => '+628****0000', 'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);
    $this->actingAs($user);

    $rolePermsBefore = DB::table('role_has_permissions')
        ->where('role_id', $role->id)
        ->pluck('permission_id')
        ->toArray();
    $userRolesBefore = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count();
    $roleAssignmentsBefore = DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->pluck('role_id')
        ->toArray();

    // Downgrade: Pro → Free
    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);

    // Role permission unchanged
    expect($role->fresh()->hasPermissionTo('user.view'))->toBeTrue();
    $rolePermsAfter = DB::table('role_has_permissions')
        ->where('role_id', $role->id)
        ->pluck('permission_id')
        ->toArray();
    expect($rolePermsAfter)->toBe($rolePermsBefore);

    // User role assignment unchanged
    $roleAssignmentsAfter = DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->pluck('role_id')
        ->toArray();
    expect($roleAssignmentsAfter)->toBe($roleAssignmentsBefore);

    // model_has_permissions unchanged
    $directAfter = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count();
    expect($directAfter)->toBe($userRolesBefore);

    // Access denied via Plan
    expect(PlanService::for($user)->allows('user.view'))->toBeFalse();
    expect($user->can('user.view'))->toBeFalse();
});

it('Scenario E — plan upgrade: role keeps permission, access restored, no sync', function () {
    $role = Role::findOrCreate('admin_bounded', 'web');
    $role->givePermissionTo('user.view');

    // Create user manually (makeBoundedUser resets role permissions)
    $user = User::create([
        'name' => 'Bounded', 'username' => 'bounded_'.uniqid(),
        'email' => 'bounded_'.uniqid().'@example.com',
        'phone' => '+628****0000', 'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);
    $this->actingAs($user);

    // Start: free plan denies
    expect($user->can('user.view'))->toBeFalse();

    // Capture state before upgrade
    $rolePermsBefore = DB::table('role_has_permissions')
        ->where('role_id', $role->id)
        ->pluck('permission_id')
        ->toArray();
    $directBefore = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count();

    // Upgrade: Free → Pro
    activatePro(['limits' => ['allowed_permissions' => ['user.view']]]);

    // Role permission unchanged (no sync happened)
    $rolePermsAfter = DB::table('role_has_permissions')
        ->where('role_id', $role->id)
        ->pluck('permission_id')
        ->toArray();
    expect($rolePermsAfter)->toBe($rolePermsBefore);

    // No direct permissions inserted
    $directAfter = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count();
    expect($directAfter)->toBe($directBefore);

    // Access restored automatically
    expect(PlanService::for($user)->allows('user.view'))->toBeTrue();
    expect($user->can('user.view'))->toBeTrue();
});

it('Scenario F — Pennant kill switch: 404 even when Plan and Role allow', function () {
    activatePro(['limits' => ['allowed_permissions' => ['user.view']]]);

    $user = makeBoundedUser(['user.view'], 'bounded');
    $this->actingAs($user);

    // Plan allows, role allows — but kill the feature flag
    Feature::deactivate('users');

    $this->get(route('users.index'))->assertNotFound();
});

it('no direct user permission records created during any scenario', function () {
    $user = makeBoundedUser(['user.view'], 'bounded');
    $this->actingAs($user);

    // Try various operations
    activatePro();

    $direct = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $user->id)
        ->count();
    expect($direct)->toBe(0);
});

it('Scenario B — per_user mode: user license Plan overrides global', function () {
    // Set to per_user mode
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    // Normal user with Free license (default)
    $user = makeBoundedUser(['user.view', 'role.create'], 'bounded');
    $this->actingAs($user);

    // Global plan is enterprise (from beforeEach), but per_user should use user's license
    expect(PlanService::for(null, $user)->plan()->slug)->toBe('free');

    // Issue a Pro license for this user
    LicenseService::issueFor($user, 'pro', ['type' => 'manual', 'expires_at' => null]);

    cache()->flush();
    expect(PlanService::for(null, $user)->plan()->slug)->toBe('pro')
        ->and(PlanService::for(null, $user)->can('api-tokens'))->toBeTrue(); // pro has api-tokens feature
});
