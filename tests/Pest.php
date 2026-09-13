<?php

use App\Models\License;
use App\Models\Permission;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature')
    ->beforeEach(function () {
        $this->seed();
        cache()->flush();

        // CHALLENGE 3: Activate enterprise license, then patch its snapshot to
        // include ALL permissions. Seed plans leave allowed_permissions empty
        // (deny-by-default per PlanService::allows). For RBAC tests that exercise
        // role permissions, the plan must allow them. Tests needing denial
        // scenarios override this in their own beforeEach.
        $key = LicenseService::issue('enterprise');
        LicenseService::activate($key);

        // Patch snapshot: grant all permissions at the Plan boundary level.
        $license = License::where('license_key', $key)->first();
        $allPerms = Permission::pluck('name')->all();
        $license->update(['snapshot' => [
            'limits' => array_merge($license->snapshot['limits'] ?? [], ['allowed_permissions' => $allPerms]),
            'features' => $license->snapshot['features'] ?? [],
        ]]);
        cache()->flush();
    });

/*
||--------------------------------------------------------------------------
|| Expectations
||--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
