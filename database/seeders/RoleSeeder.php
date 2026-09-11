<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the base RBAC roles.
 *
 * Intentional authorization design (doc auth.md §Authorization):
 *  - super-admin + admin receive ALL permissions (god-tier).
 *  - staff gets NO direct permissions — its capabilities resolve through
 *    plan features (PlanService::can), so subscriber limits apply.
 *
 * Depends on: PermissionSeeder (must run first so syncPermissions() works).
 * Idempotent: spatie's syncPermissions replaces the set each run.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $staff = Role::findOrCreate('staff', 'web');

        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions(Permission::all());
        $staff->syncPermissions([]); // staff: entitlements via plan, not role
    }
}
