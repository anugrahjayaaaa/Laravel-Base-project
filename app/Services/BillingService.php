<?php

namespace App\Services;

use App\Models\License;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Billing entry point (doc §5). Two callers, one method each:
 *  - checkout(): user picks a paid plan -> start payment
 *  - handleWebhook(): real PG webhook -> complete payment + grant license
 *
 * Dummy mode (config('billing.fake')) short-circuits checkout() to complete
 * immediately (doc §6) — no PG, license granted straight away.
 */
final class BillingService
{
    /** Start a checkout for a paid plan. */
    public static function checkout(Plan $plan, ?int $userId = null): Payment
    {
        $payment = Payment::create([
            'plan_slug' => $plan->slug,
            'user_id' => $userId,
            'amount' => $plan->price_monthly,
            'currency' => 'IDR',
            'status' => 'pending',
            'gateway' => config('billing.gateway', 'dummy'),
        ]);

        if (! config('billing.fake')) {
            // ponytail: real PG call (Midtrans Snap) goes here — not wired yet
            return $payment;
        }

        return self::complete($payment, 'dummy-'.Str::random(12), $plan, $userId ? User::find($userId) : null);
    }

    /** Mark paid + grant license. Idempotent via status. */
    public static function complete(Payment $payment, string $gatewayRef, ?Plan $plan = null, ?User $forUser = null): Payment
    {
        if ($payment->status === 'paid') {
            return $payment; // already completed — idempotent
        }

        $payment->update(['status' => 'paid', 'gateway_ref' => $gatewayRef]);

        $plan = $plan ?? Plan::where('slug', $payment->plan_slug)->firstOrFail();

        // ponytail: dummy has no billing period -> honor plan.billing_period
        //  lifetime  => expires_at null (never expires)
        //  monthly   => expires_at +1 month from now
        $expiresAt = $plan->billing_period === 'lifetime'
            ? null
            : now()->addMonth();

        $key = LicenseService::issue($payment->plan_slug, [
            'type' => 'recurring',
            'expires_at' => $expiresAt,
            'user_id' => $payment->user_id,
        ]);

        if (Setting::get('license_mode', 'global') === 'per_user' && $forUser) {
            LicenseService::activate($key, null, $forUser);
        } else {
            LicenseService::activate($key);
        }

        return $payment->fresh();
    }

    /** Cancel a user's subscription: revoke their license + freeze (no refund, doc §10.2). */
    public static function cancelUser(User $user): void
    {
        $license = $user->licenses()->active()->latest()->first();
        if ($license) {
            LicenseService::revoke($license->license_key, 'user_canceled');
        }
        // ponytail: freeze, never delete — payment history kept for accounting
        $user->payments()->where('status', 'paid')->latest()->first()?->update(['canceled_at' => now()]);
    }

    /** The user's currently active license (or null). */
    public static function userActiveLicense(User $user): ?License
    {
        return $user->licenses()->active()->latest()->first();
    }

    /** Webhook entry (real PG). Fake mode just activates from payload. */
    public static function handleWebhook(array $payload): ?Payment
    {
        $ref = $payload['order_id'] ?? $payload['id'] ?? null;
        if (! $ref) {
            return null;
        }

        // ponytail: plan must exist; reject (don't 500) if gateway sent unknown slug
        if (! Plan::where('slug', $payload['plan_slug'] ?? '')->exists()) {
            return null;
        }

        $payment = Payment::where('gateway_ref', $ref)->first()
            ?? Payment::create([
                'plan_slug' => $payload['plan_slug'] ?? 'pro',
                'amount' => $payload['amount'] ?? 0,
                'status' => 'pending',
                'gateway' => config('billing.gateway', 'dummy'),
                'gateway_ref' => $ref,
                'payload' => $payload,
            ]);

        if (($payload['status'] ?? 'paid') === 'paid') {
            $plan = Plan::where('slug', $payload['plan_slug'])->first();

            return self::complete($payment, $ref, $plan);
        }

        return $payment;
    }
}
