<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('checkout route exists and method is reachable (dummy mode)', function () {
    config(['billing.fake' => true]);
    $user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($user);

    Plan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'price_monthly' => 99000,
        'is_active' => true, 'billing_period' => 'monthly',
        'limits' => ['max_members' => 5], 'features' => ['api-tokens'],
    ]);

    // form posts plan_slug (as plans/index.blade does)
    $this->post(route('billing.checkout'), ['plan_slug' => 'pro'])
        ->assertRedirect(route('billing.index'));

    // dummy mode should have paid immediately
    $payments = Payment::where('plan_slug', 'pro')->get();
    expect($payments->count())->toBe(1)
        ->and($payments->first()->status)->toBe('paid');
});

it('checkout rejects non-existent plan_slug (404)', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $this->post(route('billing.checkout'), ['plan_slug' => 'nonexistent'])
        ->assertNotFound();
});

it('checkout rejects free plan (price=0) — 404', function () {
    $this->actingAs(User::where('email', 'admin@laravel-base.local')->first());
    $this->post(route('billing.checkout'), ['plan_slug' => 'free'])
        ->assertNotFound();
});

it('webhook rejects invalid signature (403)', function () {
    $this->post(route('billing.webhook'), ['plan_slug' => 'free', 'status' => 'paid'])
        ->assertForbidden();
});

it('webhook with valid signature completes payment', function () {
    config(['billing.fake' => true, 'billing.webhook_secret' => 'test-secret']);
    Plan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'price_monthly' => 99000,
        'is_active' => true, 'billing_period' => 'monthly',
        'limits' => ['max_members' => 5], 'features' => ['api-tokens'],
    ]);

    $this->post(route('billing.webhook'), [
        'plan_slug' => 'pro', 'status' => 'paid', 'order_id' => 'order-test-1',
    ], [
        'X-Billing-Signature' => 'test-secret',
    ])->assertOk();

    expect(Payment::where('gateway_ref', 'order-test-1')->first()->status)->toBe('paid');
});

it('tampered plan setting reverts to free features (§10.1)', function () {
    // client edits settings.active_plan directly without valid license
    Setting::set('active_plan', 'pro');
    Setting::set('license_key', null);

    Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro', 'price_monthly' => 99000, 'is_active' => true,
        'billing_period' => 'monthly',
        'limits' => ['max_members' => 5], 'features' => ['api-tokens'],
    ]);

    // no activated license -> PlanService refuses paid features
    expect(PlanService::for()->can('api-tokens'))->toBeFalse();
    expect(Setting::get('active_plan'))->toBe('pro'); // setting persists but features locked
});

it('dashboard renders license badge', function () {
    $user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($user);

    // Global beforeEach activates enterprise license
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('License')
        ->assertSee('Lifetime');
});

it('dashboard shows recent activity feed', function () {
    $user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($user);

    activity()
        ->causedBy($user)
        ->withProperties(['model' => 'User'])
        ->log('login');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(ui('recent_activity'));
});
