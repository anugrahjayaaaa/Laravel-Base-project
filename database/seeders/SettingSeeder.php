<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds system-level reference settings (doc licensing-and-billing.md §46-49).
 *
 * Values are deterministic defaults, matched against Setting::get()/set()
 * cache-aware semantics and respected by PlanService, SetLocale,
 * RegistrationEnabled, etc. Idempotent via updateOrCreate.
 *
 * NOTE: license_secret is intentionally NOT seeded (doc §51: env-only,
 * fail-closed if missing — LicenseService::requireEnv()).
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(['key' => 'active_plan'], ['value' => 'free']);
        Setting::updateOrCreate(['key' => 'default_plan'], ['value' => 'free']);
        Setting::updateOrCreate(['key' => 'default_role'], ['value' => 'staff']);
        Setting::updateOrCreate(['key' => 'license_mode'], ['value' => 'global']);
    }
}
