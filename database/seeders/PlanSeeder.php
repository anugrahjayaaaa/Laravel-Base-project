<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the three subscription plans: free, pro, enterprise.
 *
 * Final limit schema (only keys with implemented consumers):
 *   max_features, max_members, max_storage_mb, max_permissions,
 *   max_roles, allowed_permissions
 * `0` = unlimited for numeric keys (PlanService::limit returns int; callers
 * use max(0, limit - count)). `allowed_permissions` empty = deny by default.
 *
 * Evidence: Free = user-set minimal; Pro = test-fixture baseline
 * (tests/Feature/{BillingTest,LicensingTest,QaSmokeTest}); Enterprise =
 * progressive derivation. `can_create_roles` and `max_projects` are NOT in
 * the active schema (removed — role creation gates via `role.create`
 * permission; projects not implemented).
 *
 * idempotency: updateOrCreate keyed on slug -> rerun updates, never duplicates.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $free = [
            'max_features' => 0,
            'max_members' => 0,
            'max_storage_mb' => 0,
            'max_permissions' => 0,
            'max_roles' => 0,
            'allowed_permissions' => [],
        ];
        $pro = [
            'max_features' => 3,
            'max_members' => 5,
            'max_storage_mb' => 2000,
            'max_permissions' => 10,
            'max_roles' => 3,
            'allowed_permissions' => [],
        ];
        // Enterprise: 0 = effectively unlimited (no cap enforced per key).
        // CHALLENGE 3: enterprise plan explicitly allows ALL system permissions
        // so that super-admin/admin role permissions become effective.
        $enterprise = [
            'max_features' => 0,
            'max_members' => 0,
            'max_storage_mb' => 0,
            'max_permissions' => 0,
            'max_roles' => 0,
            'allowed_permissions' => Permission::pluck('name')->all(),
        ];

        Plan::updateOrCreate(
            ['slug' => 'free'],
            ['name' => 'Free', 'price_monthly' => 0, 'is_active' => true, 'limits' => $free, 'features' => []]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            ['name' => 'Pro', 'price_monthly' => 99000, 'is_active' => true, 'limits' => $pro, 'features' => ['api-tokens', 'audit', 'telescope']]
        );

        Plan::updateOrCreate(
            ['slug' => 'enterprise'],
            ['name' => 'Enterprise', 'price_monthly' => 499000, 'is_active' => true, 'limits' => $enterprise, 'features' => ['users', 'roles', 'permissions', 'audit', 'logs', 'telescope', 'periscope', 'sessions', 'api-tokens', 'translations', 'features', 'plans', 'billing']]
        );
    }
}
