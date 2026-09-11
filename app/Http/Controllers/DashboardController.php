<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\PlanService;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $license = match (Setting::get('license_mode', 'global')) {
            'per_user' => $user->license()->first(),
            default => LicenseService::activeLicense(),
        };

        $licenseStatus = LicenseService::status($user);
        $licenseDaysLeft = LicenseService::daysLeft($user);

        // ponytail: recent audit trail for dashboard — latest 5, eager-load causer
        $recentActivity = Activity::with('causer')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', [
            'title' => 'Dashboard',
            'userCount' => User::count(),
            'roleCount' => Role::count(),
            'auditCount' => Activity::count(),
            'recentActivity' => $recentActivity,
            'licenseStatus' => $licenseStatus,
            'licenseDaysLeft' => $licenseDaysLeft,
            'activePlan' => $licenseStatus === 'none'
                ? Setting::get('active_plan', 'free')
                : PlanService::for($user)->plan()->slug,
            'license' => $license,
            'user' => $user,
        ]);
    }
}
