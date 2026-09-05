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

        return view('dashboard', [
            'title' => 'Dashboard',
            'userCount' => User::count(),
            'roleCount' => Role::count(),
            'auditCount' => Activity::count(),
            'licenseStatus' => LicenseService::status($user),
            'licenseDaysLeft' => LicenseService::daysLeft($user),
            'activePlan' => LicenseService::status($user) === 'none'
                ? Setting::get('active_plan', 'free')
                : PlanService::for($user)->plan()->slug,
            'license' => $user->license,
            'user' => $user,
        ]);
    }
}
