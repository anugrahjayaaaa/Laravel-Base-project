<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root seeder — orchestration only, no business logic.
 *
 * Order matters (FK / reference-data dependencies):
 *  1. PermissionSeeder  -> spatie permissions used by roles
 *  2. RoleSeeder        -> spatie roles + permission assignments
 *  3. PlanSeeder        -> plans referenced by SettingSeeder (active_plan)
 *  4. SettingSeeder     -> global/system defaults
 *  5. AdminUserSeeder   -> first-run super-admin (assigns 'super-admin' role)
 *  6. LanguageLineSeeder-> spatie/linguist translatable strings
 *
 * Feature flags (Laravel Pennant) are declared in AppServiceProvider +
 * config/pennant.php and default ON — no seeding required (see docs).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            PlanSeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
            LanguageLineSeeder::class,
        ]);
    }
}
