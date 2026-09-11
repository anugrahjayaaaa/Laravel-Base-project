<?php

use App\Models\License;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\BillingService;
use App\Services\LicenseService;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('dummy checkout completes and grants a lifetime license', function () {
    config(['billing.fake' => true]);
    $plan = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro', 'price_monthly' => 99000, 'is_active' => true,
        'limits' => ['max_members' => 5], 'features' => ['api-tokens'],
    ]);

    $payment = BillingService::checkout($plan, 1);

    expect($payment->status)->toBe('paid')
        ->and($payment->gateway_ref)->not->toBeNull()
        ->and(LicenseService::status())->toBe('active')
        ->and(PlanService::for()->can('api-tokens'))->toBeTrue()
        ->and(PlanService::for()->membersLeft())->toBe(5 - User::count());
});

it('webhook completes idempotently (no double license)', function () {
    config(['billing.fake' => true]);
    // Reset from global enterprise default to test pro license flow
    Setting::set('active_plan', 'free');
    Setting::set('license_key', null);
    License::query()->delete(); // clear enterprise license from global beforeEach
    Plan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'price_monthly' => 99000,
        'is_active' => true, 'limits' => ['max_members' => 5], 'features' => ['api-tokens']]);

    $ref = 'order-abc-123';
    $first = BillingService::handleWebhook(['order_id' => $ref, 'status' => 'paid', 'plan_slug' => 'pro']);
    $second = BillingService::handleWebhook(['order_id' => $ref, 'status' => 'paid', 'plan_slug' => 'pro']);

    expect($first->id)->toBe($second->id)
        ->and(License::count())->toBe(1)
        ->and(LicenseService::status())->toBe('active');
});
