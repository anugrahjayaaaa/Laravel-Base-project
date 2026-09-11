<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Payment;
use App\Models\User;
use Illuminate\View\View;

class BillingAdminController extends Controller
{
    /** Admin billing dashboard: KPIs + recent payments + active licenses. */
    public function index(): View
    {
        // ponytail: single scan for revenue + paid total + paid-this-month via conditional aggregation
        $now = now()->startOfMonth();
        $row = (array) Payment::where('status', 'paid')
            ->selectRaw('SUM(amount) as revenue')
            ->selectRaw('COUNT(*) as total_paid')
            ->selectRaw('COUNT(CASE WHEN created_at >= ? THEN 1 END) as paid_this_month', [$now])
            ->first();

        $revenue = (float) ($row['revenue'] ?? 0);
        $paidThisMonth = (int) ($row['paid_this_month'] ?? 0);
        $activeSubscribers = User::whereHas('licenses', fn ($q) => $q->active())->count();

        // ponytail: reuse one scoped query for both breakdown and listing
        $activeLicenseQuery = License::active();
        $planBreakdown = (clone $activeLicenseQuery)
            ->selectRaw('plan_slug, count(*) as total')
            ->groupBy('plan_slug')
            ->pluck('total', 'plan_slug');

        $licenses = $activeLicenseQuery->with('user')->latest()->paginate(20);
        $payments = Payment::with('user')->latest()->paginate(20);

        return view('admin.billing.index', compact(
            'revenue', 'activeSubscribers', 'paidThisMonth', 'planBreakdown',
            'payments', 'licenses'
        ));
    }
}
