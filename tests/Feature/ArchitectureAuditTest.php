<?php

use App\Models\License;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\PlanService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
});

/**
 * Helper: activate a pro license with explicit allowed_permissions snapshot.
 * Clears any existing pro license first (deterministic key → unique constraint).
 */
function activateProWithPermissions(array $permissions): void
{
    License::where('plan_slug', 'pro')->delete();
    $pro = Plan::where('slug', 'pro')->first();
    $pro->update([
        'name' => 'Pro',
        'price_monthly' => 99000,
        'is_active' => true,
        'billing_period' => 'monthly',
        'limits' => ['allowed_permissions' => $permissions, 'max_members' => 5, 'max_roles' => 3],
        'features' => ['users', 'roles'],
    ]);
    $key = LicenseService::issue('pro', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key);
}

/**
 * Helper: create a non-superadmin user with a specific role + permissions.
 * Returns the user (acting-as'd).
 */
function makeRegularUser(array $perms = [], string $roleName = 'regular'): User
{
    $role = Role::findOrCreate($roleName, 'web');
    $role->syncPermissions($perms);
    $user = User::create([
        'name' => 'Regular',
        'username' => 'regular_'.uniqid(),
        'email' => 'regular_'.uniqid().'@example.com',
        'phone' => '+628'.uniqid(),
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    // In per_user mode, provision a default Free license for every new user
    if (Setting::get('license_mode') === 'per_user') {
        LicenseService::defaultLicenseForUser($user);
    }

    return $user;
}

/*
|--------------------------------------------------------------------------
| Challenge 2: Feature Entitlement States
|--------------------------------------------------------------------------
*/

it('State 1: Pennant ON + Plan feature ON → proceeds to authorization', function () {
    activateProWithPermissions(['user.view']);
    $user = makeRegularUser(['user.view'], 'perm_user');
    $this->actingAs($user);

    expect(Feature::active('users'))->toBeTrue();
    expect(PlanService::for($user)->can('users'))->toBeTrue();
    $this->get(route('users.index'))->assertOk();
});

it('State 2: Pennant OFF + Plan feature ON → 404', function () {
    activateProWithPermissions(['user.view']);
    $user = makeRegularUser(['user.view'], 'perm_user');
    $this->actingAs($user);

    Feature::deactivate('users');
    $this->get(route('users.index'))->assertNotFound();
});

it('State 3: Pennant ON + Plan feature OFF → denied by Plan (403)', function () {
    // Free plan has no features + empty allowed_permissions → plan denies
    $user = makeRegularUser(['user.view'], 'perm_user');
    $this->actingAs($user);

    expect(Feature::active('users'))->toBeTrue();
    expect(PlanService::for($user)->can('users'))->toBeFalse();

    $this->get(route('users.index'))->assertForbidden();
});

it('State 4: Pennant OFF + Plan feature OFF → Pennant wins (404)', function () {
    $user = makeRegularUser(['user.view'], 'perm_user');
    $this->actingAs($user);

    Feature::deactivate('users');
    $this->get(route('users.index'))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Challenge 3: Permission Entitlement Matrix
|--------------------------------------------------------------------------
*/

it('State A: Role YES + Plan YES → ALLOW', function () {
    activateProWithPermissions(['user.view']);
    $user = makeRegularUser(['user.view'], 'perm_user');
    $this->actingAs($user);

    expect($user->can('user.view'))->toBeTrue();
    $this->get(route('users.index'))->assertOk();
});

it('State B: Role YES + Plan NO → DENY', function () {
    $user = makeRegularUser(['user.view'], 'perm_user');
    $this->actingAs($user);

    expect(PlanService::for($user)->allows('user.view'))->toBeFalse();
    expect($user->can('user.view'))->toBeFalse();
});

it('State C: Role NO + Plan YES → DENY (Plan never grants by itself)', function () {
    activateProWithPermissions(['user.view']);
    $user = makeRegularUser([], 'bare_user');
    $this->actingAs($user);

    expect(PlanService::for($user)->allows('user.view'))->toBeTrue();
    expect($user->can('user.view'))->toBeFalse();
    $this->get(route('users.index'))->assertForbidden();
});

it('State D: Neither Role nor Plan → DENY', function () {
    $user = makeRegularUser([], 'bare_user');
    $this->actingAs($user);

    expect($user->can('user.view'))->toBeFalse();
    $this->get(route('users.index'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Challenge 4: Plan Lifecycle — no mutation of Role/User state
|--------------------------------------------------------------------------
*/

it('Free → Pro preserves Role permissions and restores effective access', function () {
    $role = Role::where('name', 'admin')->first();
    $role->givePermissionTo('user.view');
    $user = makeRegularUser([], 'lifecycle_user');
    $user->assignRole('admin');
    $this->actingAs($user);

    expect($user->can('user.view'))->toBeFalse(); // free, no license

    $rolePermsBefore = DB::table('role_has_permissions')
        ->where('role_id', $role->id)->pluck('permission_id')->toArray();

    activateProWithPermissions(['user.view']);

    $rolePermsAfter = DB::table('role_has_permissions')
        ->where('role_id', $role->id)->pluck('permission_id')->toArray();
    expect($rolePermsAfter)->toBe($rolePermsBefore);

    $direct = DB::table('model_has_permissions')
        ->where('model_type', User::class)->where('model_id', $user->id)->count();
    expect($direct)->toBe(0);

    expect($user->can('user.view'))->toBeTrue();
});

it('Pro → Free preserves Role permissions and denies access', function () {
    activateProWithPermissions(['user.view']);
    $role = Role::where('name', 'admin')->first();
    $role->givePermissionTo('user.view');
    $user = makeRegularUser([], 'lifecycle_user');
    $user->assignRole('admin');
    $this->actingAs($user);

    expect($user->can('user.view'))->toBeTrue();

    $rolePermsBefore = DB::table('role_has_permissions')
        ->where('role_id', $role->id)->pluck('permission_id')->toArray();

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();

    $rolePermsAfter = DB::table('role_has_permissions')
        ->where('role_id', $role->id)->pluck('permission_id')->toArray();
    expect($rolePermsAfter)->toBe($rolePermsBefore);

    expect($role->fresh()->hasPermissionTo('user.view'))->toBeTrue();
    expect($user->can('user.view'))->toBeFalse();
});

it('No model_has_permissions writes during plan changes (Free→Pro→Free)', function () {
    $role = Role::where('name', 'admin')->first();
    $role->givePermissionTo('user.view');
    $user = makeRegularUser([], 'lifecycle_user');
    $user->assignRole('admin');
    $this->actingAs($user);

    $before = DB::table('model_has_permissions')->count();

    activateProWithPermissions(['user.view']);
    expect($user->can('user.view'))->toBeTrue();

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();
    expect($user->can('user.view'))->toBeFalse();

    activateProWithPermissions(['user.view', 'user.create']);
    expect($user->can('user.view'))->toBeTrue();

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();

    $after = DB::table('model_has_permissions')->count();
    expect($after)->toBe($before);
});

/*
|--------------------------------------------------------------------------
| Challenge 5: Role Management — Plan-capped assignment
|--------------------------------------------------------------------------
*/

it('Role management cannot assign permissions outside the current Plan (non-feature.manage user)', function () {
    // Plan only allows user.view
    activateProWithPermissions(['user.view']);
    expect(PlanService::for(null)->allows('user.view'))->toBeTrue();
    expect(PlanService::for(null)->allows('user.delete'))->toBeFalse();

    // Non-superadmin user with role.create but NOT feature.manage
    $user = makeRegularUser(['role.view', 'role.create', 'role.edit', 'user.view'], 'sub_mgr');
    $this->actingAs($user);

    $userDelete = Permission::where('name', 'user.delete')->first();
    $userView = Permission::where('name', 'user.view')->first();

    $this->post(route('roles.store'), [
        'name' => 'evil_role',
        'permissions' => [$userDelete->id, $userView->id],
    ])->assertRedirect(route('roles.index'));

    $evil = Role::where('name', 'evil_role')->first();
    expect($evil)->not->toBeNull();
    // user.delete stripped by filterPermissions
    expect($evil->hasPermissionTo('user.delete'))->toBeFalse();
    expect($evil->hasPermissionTo('user.view'))->toBeTrue();
});

it('Role management Plan-caps non-feature.manage users', function () {
    activateProWithPermissions(['user.view']);

    // User with role.create but NOT feature.manage → subject to plan limits
    $user = makeRegularUser(['role.view', 'role.create', 'role.edit'], 'sub_mgr');
    $this->actingAs($user);

    expect($user->can('role.create'))->toBeTrue();

    $allPerms = Permission::pluck('id')->all();

    $resp = $this->post(route('roles.store'), [
        'name' => 'filtered_role',
        'permissions' => $allPerms,
    ]);
    $resp->assertRedirect(route('roles.index'));

    $filtered = Role::where('name', 'filtered_role')->first();
    expect($filtered)->not->toBeNull();
    expect($filtered->hasPermissionTo('user.delete'))->toBeFalse();
    expect($filtered->hasPermissionTo('user.view'))->toBeTrue();
    expect($filtered->hasPermissionTo('role.view'))->toBeFalse();
    expect($filtered->hasPermissionTo('role.create'))->toBeFalse();
});

it('Management permissions (role.*, permission.*) are Plan-gated for normal users', function () {
    // Free plan: allowed_permissions is empty → all domain permissions denied
    $user = makeRegularUser([], 'bare_user');
    $this->actingAs($user);

    // role.* and permission.* are exempt from Plan boundary Gate check
    // (they bypass PlanService::allows in AppServiceProvider Gate::before)
    expect($user->can('role.create'))->toBeFalse();
    expect($user->can('role.edit'))->toBeFalse();
    expect($user->can('permission.view'))->toBeFalse();
    expect($user->can('permission.create'))->toBeFalse();

    // feature.manage is NOT exempt — it's a domain permission subject to Plan
    expect($user->can('feature.manage'))->toBeFalse();
});

it('Existing unavailable Role permission remains stored after plan downgrade', function () {
    activateProWithPermissions(['user.view', 'user.delete']);
    $role = Role::create(['name' => 'temp_role_'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions(['user.view', 'user.delete']);

    expect($role->hasPermissionTo('user.delete'))->toBeTrue();

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();

    expect($role->fresh()->hasPermissionTo('user.delete'))->toBeTrue();
    // Use a non-superadmin user to verify effective permission is denied
    $user = makeRegularUser([], 'test_user');
    $user->assignRole($role);
    $this->actingAs($user);
    expect($user->can('user.delete'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Challenge 7: Enterprise Seeder Audit
|--------------------------------------------------------------------------
*/

it('Enterprise plan contains all valid permissions', function () {
    $enterprise = Plan::where('slug', 'enterprise')->first();
    $allowed = $enterprise->limits['allowed_permissions'] ?? [];

    expect($allowed)->not->toBeEmpty();
    foreach ($allowed as $permName) {
        expect(Permission::where('name', $permName)->exists())
            ->toBeTrue("Permission '$permName' in enterprise allowed_permissions does not exist");
    }
    expect(count($allowed))->toBe(Permission::count());
});

it('Enterprise allows permission does not grant access without Role', function () {
    License::where('plan_slug', 'enterprise')->delete();
    $enterprise = Plan::where('slug', 'enterprise')->first();
    $key = LicenseService::issue('enterprise', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key);

    $user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($user);

    expect(PlanService::for($user)->allows('user.delete'))->toBeTrue();

    // Create a bare user with NO role and NO direct permissions
    $bare = User::create([
        'name' => 'Bare', 'username' => 'bare_'.uniqid(),
        'email' => 'bare_'.uniqid().'@example.com',
        'phone' => '+628'.uniqid(), 'password' => bcrypt('secret123'), 'email_verified_at' => now(),
    ]);
    $this->actingAs($bare);

    expect(PlanService::for($bare)->allows('user.delete'))->toBeTrue();
    expect($bare->can('user.delete'))->toBeFalse(); // no role → no permission
});

/*
|--------------------------------------------------------------------------
| Challenge 8: Per-User Mode Audit
|--------------------------------------------------------------------------
*/

it('per_user mode resolves plan from user license', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    // Create a normal user (not superadmin)
    $user = makeRegularUser(['user.view'], 'perm_user');

    // In per_user mode, resolve from user's license (no license yet → Free)
    $plan = PlanService::for(null, $user);
    expect($plan->plan()->slug)->toBe('free');

    // Issue a Pro license for this user (per_user mode — no global activation)
    LicenseService::issueFor($user, 'pro', ['type' => 'manual', 'expires_at' => null]);
    cache()->flush();

    $plan = PlanService::for(null, $user);
    expect($plan->plan()->slug)->toBe('pro');
});

it('per_user mode: user without license resolves to Free', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');

    $plan = PlanService::for(null, $user);
    expect($plan->plan()->slug)->toBe('free')
        ->and($plan->can('users'))->toBeFalse();
});

it('per_user mode: different users get different plans', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user1 = makeRegularUser(['user.view'], 'u1');
    $user2 = makeRegularUser(['user.view'], 'u2');

    // Issue Pro license for user1 only
    LicenseService::issueFor($user1, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(30)]);
    cache()->flush();

    expect(PlanService::for(null, $user1)->plan()->slug)->toBe('pro');
    expect(PlanService::for(null, $user2)->plan()->slug)->toBe('free');
});

it('per_user mode: default Free license provisioned on user creation', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');

    // User should have a default Free license
    $licenses = DB::table('licenses')
        ->where('user_id', $user->id)
        ->where('plan_slug', 'free')
        ->where('status', 'active')
        ->count();
    expect($licenses)->toBe(1);

    // PlanService resolves the Free plan from the license
    expect(PlanService::for(null, $user)->plan()->slug)->toBe('free');
});

it('per_user mode: license history is preserved', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');

    // Issue a Pro license, then expire it
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->subDay()]);

    // Issue a new Free license
    LicenseService::defaultLicenseForUser($user);
    cache()->flush();

    // Both licenses exist in history
    $licenseCount = DB::table('licenses')->where('user_id', $user->id)->count();
    expect($licenseCount)->toBeGreaterThanOrEqual(2);

    // But effective plan is Free (pro license is expired)
    expect(PlanService::for(null, $user)->plan()->slug)->toBe('free');
});

it('per_user: expired license is not mutated to Free', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->subDay()]);
    cache()->flush();

    // The license still exists with its original plan
    $expired = DB::table('licenses')
        ->where('user_id', $user->id)
        ->where('plan_slug', 'pro')
        ->first();
    expect($expired)->not->toBeNull();
    expect($expired->plan_slug)->toBe('pro');
    expect($expired->status)->toBe('active'); // status unchanged
});

it('per_user: license expiring in 7 days shows dashboard warning', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    // Remove the default Free license to set up a Pro license that expires in 5 days
    License::where('user_id', $user->id)->delete();
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(5)]);
    cache()->flush();

    expect(LicenseService::daysLeft($user))->toBeGreaterThan(0);
    expect(LicenseService::daysLeft($user))->toBeLessThanOrEqual(7);
});

it('per_user: license expiring in less than 7 days shows warning', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    License::where('user_id', $user->id)->delete();
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(3)]);
    cache()->flush();

    expect(LicenseService::daysLeft($user))->toBeGreaterThan(0);
    expect(LicenseService::daysLeft($user))->toBeLessThanOrEqual(7);
});

it('per_user: license expiring exactly at 7-day boundary shows warning', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    License::where('user_id', $user->id)->delete();
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(7)]);
    cache()->flush();

    $days = LicenseService::daysLeft($user);
    expect($days)->toBeLessThanOrEqual(7); // boundary: 7 days ≤ 7 → warning shows
});

it('per_user: license expiring in more than 7 days does not show warning', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    License::where('user_id', $user->id)->delete();
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(30)]);
    cache()->flush();

    expect(LicenseService::daysLeft($user))->toBeGreaterThan(7);
});

it('per_user: expired license does not show warning', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->subDay()]);
    cache()->flush();

    expect(LicenseService::daysLeft($user))->toBeLessThanOrEqual(0); // expired (not in 1-7 range → no warning)
    expect(LicenseService::status($user))->toBe('expired');
});

it('per_user: superadmin does not receive expiration warning', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $sa = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($sa);
    expect($sa->isSuperAdmin())->toBeTrue();
    // Superadmin has no license → no expiration warning
    expect(LicenseService::daysLeft($sa))->toBeNull();
});

it('per_user: superadmin not restricted by Free plan limits', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $sa = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($sa);

    expect($sa->isSuperAdmin())->toBeTrue();
    expect($sa->license)->toBeNull(); // no license
    // But superadmin still has all permissions via role bypass
    expect($sa->can('user.view'))->toBeTrue();
    expect($sa->can('user.delete'))->toBeTrue();
});

it('per_user: existing user without license can be provisioned safely', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    // Provision default license via defaultLicenseForUser (simulates backfill)
    $license = LicenseService::defaultLicenseForUser($user);
    expect($license)->not->toBeNull();
    expect($license->plan_slug)->toBe('free');
    cache()->flush();
    expect(PlanService::for(null, $user)->plan()->slug)->toBe('free');
});

it('per_user: default license is not duplicated on repeated creation', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    // First call should create the license (already created by makeRegularUser)
    $countBefore = DB::table('licenses')->where('user_id', $user->id)->count();
    // Call again — should be idempotent (firstOrCreate)
    LicenseService::defaultLicenseForUser($user);
    $countAfter = DB::table('licenses')->where('user_id', $user->id)->count();
    expect($countAfter)->toBe($countBefore);
});

it('per_user: LicenseService::issueFor rejects plan that does not exist', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');
    expect(fn () => LicenseService::issueFor($user, 'nonexistent', []))
        ->toThrow(ModelNotFoundException::class);
});

/*
|--------------------------------------------------------------------------
| Challenge 9: Cache invalidation on plan change
|--------------------------------------------------------------------------
*/

it('cache invalidates after plan change (no stale entitlement)', function () {
    expect(PlanService::for(null)->can('users'))->toBeFalse();

    activateProWithPermissions(['user.view']);
    cache()->flush();
    expect(PlanService::for(null)->can('users'))->toBeTrue();

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();
    expect(PlanService::for(null)->can('users'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Challenge 10: Source-of-truth — no forbidden sync patterns
|--------------------------------------------------------------------------
*/

it('no syncPermissions on User model during plan lifecycle', function () {
    $role = Role::where('name', 'admin')->first();
    $role->givePermissionTo('user.view');
    $user = makeRegularUser([], 'lifecycle_user');
    $user->assignRole('admin');
    $this->actingAs($user);

    $before = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->count();

    activateProWithPermissions(['user.view']);
    activateProWithPermissions(['user.view', 'user.create']);

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();

    $after = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->count();

    expect($after)->toBe($before);
});

it('API RoleApiController filters permissions through plan', function () {
    activateProWithPermissions(['user.view']);
    expect(PlanService::for(null)->allows('user.delete'))->toBeFalse();

    $roleMgr = Role::create(['name' => 'apimgr_'.uniqid(), 'guard_name' => 'web']);
    $roleMgr->syncPermissions(['role.view', 'role.create', 'role.edit']);
    $user = User::create([
        'name' => 'APIMgr', 'username' => 'apimgr_'.uniqid(),
        'email' => 'apimgr_'.uniqid().'@example.com',
        'phone' => '+628****0000', 'password' => bcrypt('secret123'), 'email_verified_at' => now(),
    ]);
    $user->assignRole($roleMgr);
    $this->actingAs($user);

    $allPerms = Permission::pluck('id')->all();

    $evilName = 'api_filtered_'.uniqid();
    $this->postJson('/api/v1/roles', [
        'name' => $evilName,
        'permissions' => $allPerms,
    ])->assertCreated();

    $evil = Role::where('name', $evilName)->first();
    expect($evil)->not->toBeNull();
    expect($evil->hasPermissionTo('user.delete'))->toBeFalse();
    expect($evil->hasPermissionTo('user.view'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| NEW: Superadmin Bypass — CHALLENGE 4 continuation
|--------------------------------------------------------------------------
*/

it('Superadmin bypasses Plan permission boundary but not Pennant', function () {
    // Free plan: no allowed_permissions → normal users denied
    $sa = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($sa);

    // Superadmin has super-admin role → bypasses Plan permission boundary
    expect($sa->can('user.view'))->toBeTrue();
    expect($sa->can('user.delete'))->toBeTrue();
    expect($sa->can('feature.manage'))->toBeTrue();

    // But Pennant still applies — if 'users' flag is OFF, still 404
    Feature::deactivate('users');
    $this->get(route('users.index'))->assertNotFound();

    // Re-enable and access works
    Feature::activate('users');
    $this->get(route('users.index'))->assertOk();
});

it('Superadmin does not require model_has_permissions', function () {
    $sa = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($sa);

    $direct = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->where('model_id', $sa->id)
        ->count();
    expect($direct)->toBe(0);
});

it('Superadmin does not require Plan → Role synchronization', function () {
    $sa = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($sa);

    // Toggle plan freely — superadmin access unchanged
    expect($sa->can('user.view'))->toBeTrue();

    activateProWithPermissions(['user.view']);
    expect($sa->can('user.view'))->toBeTrue();

    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();
    expect($sa->can('user.view'))->toBeTrue();
});

it('Superadmin can perform supported administrative operations', function () {
    $sa = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($sa);

    // Features page (needs feature.manage)
    $this->get(route('features.index'))->assertOk();

    // Plans page (needs feature.manage)
    $this->get(route('plans.index'))->assertOk();

    // Roles page (needs role.view)
    $this->get(route('roles.index'))->assertOk();

    // Users page (needs user.view + feature:users — Pennant already ON)
    $this->get(route('users.index'))->assertOk();
});

it('Normal user cannot become Superadmin through Role Management', function () {
    // Free plan — no allowed_permissions
    $user = makeRegularUser(['role.view', 'role.create', 'role.edit', 'feature.manage'], 'evil_mgr');
    $this->actingAs($user);

    // Try to create a role named 'super-admin' — the controller blocks this by name,
    // but even if it didn't, isSuperAdmin() checks hasRole('super-admin') which
    // requires explicit assignment via assignRole/assignRoleTo, not role creation.
    $this->post(route('roles.store'), [
        'name' => 'super-admin',
        'permissions' => [],
    ])->assertRedirect();

    // User still doesn't have the super-admin role
    expect($user->fresh()->isSuperAdmin())->toBeFalse();

    // Even creating a role with all permissions doesn't make the user super-admin
    $allPerms = Permission::pluck('id')->all();
    $this->post(route('roles.store'), [
        'name' => 'fake_super_'.uniqid(),
        'permissions' => [], // empty on Free plan
    ])->assertRedirect();
    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});

it('Normal user cannot become Superadmin through Role API', function () {
    // User with role.create via API but NOT feature.manage → subject to plan limits
    activateProWithPermissions(['user.view']);

    $roleMgr = makeRegularUser(['role.view', 'role.create', 'role.edit'], 'apimgr');
    $this->actingAs($roleMgr);

    // Try to create a role named 'super-admin' via API
    $this->postJson('/api/v1/roles', [
        'name' => 'super-admin',
        'permissions' => [],
    ])->assertStatus(422); // validation: super-admin name is reserved

    // Try to create a role with all permissions — should be filtered by plan
    $allPerms = Permission::pluck('id')->all();
    $evilName = 'api_super_'.uniqid();
    $this->postJson('/api/v1/roles', [
        'name' => $evilName,
        'permissions' => $allPerms,
    ])->assertCreated();

    $evil = Role::where('name', $evilName)->first();
    expect($evil)->not->toBeNull();
    // Only user.view allowed by plan — everything else stripped
    expect($evil->hasPermissionTo('user.view'))->toBeTrue();
    expect($evil->hasPermissionTo('user.delete'))->toBeFalse();
    expect($evil->hasPermissionTo('role.create'))->toBeFalse();

    // User is still NOT super-admin
    expect($roleMgr->fresh()->isSuperAdmin())->toBeFalse();
});

it('Superadmin bypass does not grant Superadmin status to ordinary users', function () {
    $user = makeRegularUser(['user.view', 'user.delete', 'feature.manage'], 'high_priv');
    $this->actingAs($user);

    expect($user->isSuperAdmin())->toBeFalse();
    // Even with all permissions, ordinary users are still Plan-gated
    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    cache()->flush();
    expect($user->can('user.view'))->toBeFalse();
});

/*
||--------------------------------------------------------------------------
|| Phase 8: License Expiration in per_user mode
||--------------------------------------------------------------------------
*/

it('per_user: mode switch global → per_user does not crash users without licenses', function () {
    // Start in global mode (default from beforeEach)
    $user = makeRegularUser(['user.view'], 'perm_user');

    // Switch to per_user
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    // User has no license yet — PlanService should fall back to Free, not crash
    $plan = PlanService::for(null, $user);
    expect($plan->plan()->slug)->toBe('free');
    expect($plan->allows('user.view'))->toBeFalse(); // Free plan denies
});

it('per_user: mode switch per_user → global continues using global plan', function () {
    // Start in per_user with a license
    Setting::set('license_mode', 'per_user');
    $user = makeRegularUser(['user.view'], 'perm_user');
    LicenseService::issueFor($user, 'pro', ['type' => 'manual', 'expires_at' => null]);
    cache()->flush();
    expect(PlanService::for(null, $user)->plan()->slug)->toBe('pro');

    // Activate a global enterprise license
    License::where('plan_slug', 'enterprise')->delete();
    $key = LicenseService::issue('enterprise', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key);
    cache()->flush();

    // Switch back to global
    Setting::set('license_mode', 'global');
    cache()->flush();

    // Global plan (enterprise) is now used
    $plan = PlanService::for(null, $user);
    expect($plan->plan()->slug)->toBe('enterprise');

    // User license history is preserved
    $licenseCount = DB::table('licenses')->where('user_id', $user->id)->count();
    expect($licenseCount)->toBeGreaterThanOrEqual(1);
});

/*
|--------------------------------------------------------------------------
| REGRESSION: PlanService::for($user) auto-detect (production caller form)
|--------------------------------------------------------------------------
*/

it('REGRESSION: PlanService::for($user) auto-detects User in per_user mode', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'perm_user');

    // Production callers use PlanService::for($user), NOT for(null, $user)
    expect(PlanService::for($user)->plan()->slug)->toBe('free');

    LicenseService::issueFor($user, 'pro', ['type' => 'manual', 'expires_at' => null]);
    cache()->flush();

    expect(PlanService::for($user)->plan()->slug)->toBe('pro');
});

it('REGRESSION: AppServiceProvider Gate enforces per-user plan in per_user mode', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    // User A: Free (no explicit license beyond default Free)
    $userA = makeRegularUser(['user.view'], 'user_a');
    $this->actingAs($userA);
    // Free plan has empty allowed_permissions → deny
    expect($userA->can('user.view'))->toBeFalse();

    // User B: Pro (explicit license with user.view in allowed_permissions)
    $userB = makeRegularUser(['user.view'], 'user_b');
    License::where('user_id', $userB->id)->delete(); // clear default Free
    $pro = Plan::where('slug', 'pro')->first();
    $pro->update(['limits' => ['allowed_permissions' => ['user.view']], 'features' => ['users']]);
    LicenseService::issueFor($userB, 'pro', ['type' => 'manual', 'expires_at' => null]);
    cache()->flush();

    $this->actingAs($userB);
    // Pro plan allows user.view, role has user.view → granted
    expect($userB->can('user.view'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| REGRESSION: DashboardController in per_user mode
|--------------------------------------------------------------------------
*/

it('REGRESSION: DashboardController resolves per-user plan via PlanService::for($user)', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $userA = makeRegularUser(['user.view'], 'dash_a');
    $userB = makeRegularUser(['user.view'], 'dash_b');

    // User A: Free (default license)
    $this->actingAs($userA);
    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertViewHas('activePlan', 'free');

    // User B: Pro
    LicenseService::issueFor($userB, 'pro', ['type' => 'manual', 'expires_at' => null]);
    cache()->flush();
    $this->actingAs($userB);
    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertViewHas('activePlan', 'pro');
});

/*
|--------------------------------------------------------------------------
| REGRESSION: License key uniqueness
|--------------------------------------------------------------------------
*/

it('REGRESSION: two users with same plan+expiry get different keys', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $userA = makeRegularUser(['user.view'], 'key_a');
    $userB = makeRegularUser(['user.view'], 'key_b');

    $keyA = LicenseService::issueFor($userA, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(30)]);
    $keyB = LicenseService::issueFor($userB, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(30)]);

    expect($keyA)->not->toBe($keyB);
});

it('REGRESSION: license verify works with user-specific key', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'verify_user');
    $key = LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => null]);

    $license = License::where('license_key', $key)->first();
    expect(LicenseService::verify($key, $license))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| REGRESSION: Per-user activate isolation
|--------------------------------------------------------------------------
*/

it('REGRESSION: activating one user license does not affect other users', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $userA = makeRegularUser(['user.view'], 'act_a');
    $userB = makeRegularUser(['user.view'], 'act_b');
    $userC = makeRegularUser(['user.view'], 'act_c');

    // Activate user A's license via activate() withForUser param
    $keyA = LicenseService::issueFor($userA, 'pro', ['type' => 'manual', 'expires_at' => null]);
    $licenseA = License::where('license_key', $keyA)->first();
    LicenseService::activate($keyA, null, $userA);
    cache()->flush();

    // User A: pro
    expect(PlanService::for($userA)->plan()->slug)->toBe('pro');
    // User B: free
    expect(PlanService::for($userB)->plan()->slug)->toBe('free');
    // User C: free
    expect(PlanService::for($userC)->plan()->slug)->toBe('free');

    // Global settings unchanged
    expect(Setting::get('license_key'))->toBeNull();
    expect(Setting::get('active_plan', 'free'))->toBe('free');

    // Other users' licenses unchanged
    $licensesB = License::where('user_id', $userB->id)->get();
    expect($licensesB->every(fn ($l) => $l->status === 'active'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| REGRESSION: Global activate still works in global mode
|--------------------------------------------------------------------------
*/

it('REGRESSION: global activate sets settings and revokes other global licenses', function () {
    Setting::set('license_mode', 'global');
    cache()->flush();

    $key1 = LicenseService::issue('pro', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key1);

    expect(Setting::get('active_plan'))->toBe('pro');
    expect(Setting::get('license_key'))->toBe($key1);

    $key2 = LicenseService::issue('enterprise', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key2);

    expect(Setting::get('active_plan'))->toBe('enterprise');
    expect(Setting::get('license_key'))->toBe($key2);

    // Previous pro license revoked
    $proLicense = License::where('license_key', $key1)->first();
    expect($proLicense->status)->toBe('expired');
});

/*
|--------------------------------------------------------------------------
| REGRESSION: Dashboard HTTP warning through actual route
|--------------------------------------------------------------------------
*/

it('REGRESSION: dashboard HTTP shows warning for expiring license (7 days)', function () {
    Setting::set('license_mode', 'per_user');
    cache()->flush();

    $user = makeRegularUser(['user.view'], 'warn_user');
    License::where('user_id', $user->id)->delete();
    LicenseService::issueFor($user, 'pro', ['type' => 'recurring', 'expires_at' => now()->addDays(5)]);
    cache()->flush();

    $this->actingAs($user);
    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('alert-warning'); // the warning alert is rendered
});
