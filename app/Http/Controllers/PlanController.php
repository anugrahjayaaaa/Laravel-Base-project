<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plan\PlanRequest;
use App\Models\Permission;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::orderBy('price_monthly')->get();

        return view('plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('plans.form', [
            'plan' => null,
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = Plan::create($request->toPlanData());
        PlanService::syncPermissionsForPlan($plan);

        return redirect()->route('plans.index')->with('success', __('messages.plan_created'));
    }

    public function edit(Plan $plan): View
    {
        return view('plans.form', [
            'plan' => $plan,
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->toPlanData());
        PlanService::syncPermissionsForPlan($plan);

        return redirect()->route('plans.index')->with('success', __('messages.plan_updated'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // ponytail: never delete 'free' — it is the baseline
        if ($plan->slug === 'free') {
            return redirect()->route('plans.index')->with('error', __('messages.plan_free_protected'));
        }
        $plan->delete();

        return redirect()->route('plans.index')->with('success', __('messages.plan_deleted'));
    }
}
