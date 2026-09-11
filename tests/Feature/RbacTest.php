<?php

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseService;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($this->user);
});

it('lists roles', function () {
    $this->get(route('roles.index'))->assertOk();
});

it('creates a role with permissions', function () {
    $perm = Permission::first();
    $this->post(route('roles.store'), [
        'name' => 'editor',
        'permissions' => [$perm->id],
    ])->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'editor')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo($perm->name))->toBeTrue();
});

it('subscriber without role.create permission cannot create roles', function () {
    // role creation is gated by the `role.create` permission (FormRequest authz),
    // not a separate plan flag (can_create_roles removed).
    $subRole = Role::create(['name' => 'sub_role', 'guard_name' => 'web']);
    $subRole->syncPermissions(['role.view', 'user.view']); // no role.create
    $plain = User::create([
        'name' => 'Plain', 'username' => 'plain', 'email' => 'plain@example.com',
        'phone' => '+628****0002', 'password' => bcrypt('x'), 'email_verified_at' => now(),
    ]);
    $plain->assignRole($subRole);

    $this->actingAs($plain);
    $this->post(route('roles.store'), ['name' => 'denied'])
        ->assertForbidden();
});

it('RBAC-01: user.view-only subscriber cannot mutate users (create/edit/delete)', function () {
    $subRole = Role::create(['name' => 'ro_user', 'guard_name' => 'web']);
    $subRole->syncPermissions('user.view');
    $plain = User::create([
        'name' => 'Plain2', 'username' => 'plain2', 'email' => 'plain2@example.com',
        'phone' => '+628****0010', 'password' => bcrypt('x'), 'email_verified_at' => now(),
    ]);
    $plain->assignRole($subRole);

    $this->actingAs($plain);
    // ponytail: user.view-only subscriber must be blocked from all user mutations.
    $target = User::factory()->create(['username' => 'target'.time(), 'name' => 'T']);
    $this->get(route('users.create'))->assertForbidden();
    $this->post(route('users.store'), [])->assertForbidden();
    $this->put(route('users.update', $target), ['name' => 'X', 'email' => $target->email, 'username' => $target->username])->assertForbidden();
    $this->delete(route('users.destroy', $target))->assertForbidden();
});

it('subscriber with roles feature can create roles but permissions are filtered', function () {
    Plan::updateOrCreate(
        ['slug' => 'pro'],
        [
            'name' => 'Pro', 'price_monthly' => 99000, 'is_active' => true,
            'billing_period' => 'monthly',
            'limits' => ['max_members' => 5, 'max_roles' => 3, 'allowed_permissions' => ['user.view']],
            'features' => ['users', 'roles'],
        ]
    );
    // activate the pro license on this instance so PlanService::for() sees it
    // ponytail: issue() reads snapshot from the Plan row + writes settings
    $key = LicenseService::issue('pro', ['type' => 'manual', 'expires_at' => null]);
    LicenseService::activate($key);

    $sub = User::create([
        'name' => 'Sub', 'username' => 'sub', 'email' => 'sub@example.com',
        'phone' => '+628****0003', 'password' => bcrypt('x'), 'email_verified_at' => now(),
    ]);
    // subscriber with role.create but NOT feature.manage -> subject to plan limits
    $subRole = Role::where('name', 'admin')->first(); // admin role has role.create + feature.manage
    // ponytail: admin role has feature.manage (bypass). Create a sub-role without it.
    $subRole = Role::create(['name' => 'plan_sub', 'guard_name' => 'web']);
    $subRole->syncPermissions(['role.create', 'role.view', 'user.view', 'user.create']);
    $sub->assignRole($subRole);

    $this->actingAs($sub);
    $allPerms = Permission::pluck('id')->all();
    $this->post(route('roles.store'), ['name' => 'limited', 'permissions' => $allPerms])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'limited')->first();
    expect($role)->not->toBeNull();
    // only user.view was allowed; other permissions filtered out
    expect($role->hasPermissionTo('user.view'))->toBeTrue();
    expect($role->hasPermissionTo('role.create'))->toBeFalse();
});

it('prevents deleting super-admin', function () {
    $sa = Role::where('name', 'super-admin')->first();
    $this->delete(route('roles.destroy', $sa))->assertRedirect();
    expect(Role::where('name', 'super-admin')->exists())->toBeTrue();
});

it('searches roles by name', function () {
    Role::create(['name' => 'zztemp_role_alpha', 'guard_name' => 'web']);
    $this->get(route('roles.index', ['q' => 'zztemp_role_alpha']))
        ->assertOk()
        ->assertSee('zztemp_role_alpha')
        ->assertDontSee('super-admin');
});

it('searches permissions by name', function () {
    Permission::create(['name' => 'zztemp_perm_alpha', 'guard_name' => 'web']);
    $this->get(route('permissions.index', ['q' => 'zztemp_perm_alpha']))
        ->assertOk()
        ->assertSee('zztemp_perm_alpha');
});
