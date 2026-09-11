<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\BillingCancelRequest;
use App\Models\License;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BillingController extends Controller
{
    /** User billing portal: current plan, active license, payment history. */
    public function index(): View
    {
        $user = auth()->user();
        $license = BillingService::userActiveLicense($user);
        $plan = $license ? Plan::where('slug', $license->plan_slug)->first() : null;
        $payments = $user->payments()->latest()->paginate(10);

        return view('billing.index', compact('user', 'license', 'plan', 'payments'));
    }

    /** Cancel the current user's subscription (revoke + freeze). */
    public function cancel(BillingCancelRequest $request): RedirectResponse
    {
        BillingService::cancelUser($request->user());

        return redirect()->route('billing.index')
            ->with('success', __('messages.subscription_canceled'));
    }

    /** Download a dummy invoice PDF for a payment. */
    public function invoice(Payment $payment): Response
    {
        abort_unless($payment->user_id === auth()->id(), 403);

        $pdf = Pdf::loadView('billing.invoice', compact('payment'));

        return $pdf->download($payment->invoice_no.'.pdf');
    }

    /**
     * Start a checkout for a paid plan. In dummy mode (BILLING_FAKE=true) the
     * license is granted immediately; real PG mode delegates to BillingService::checkout()
     * which returns a pending Payment + snap token to redirect to.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $plan = Plan::where('slug', $request->input('plan_slug'))
            ->where('is_active', true)
            ->where('price_monthly', '>', 0)
            ->firstOrFail();

        $payment = BillingService::checkout($plan, $request->user()->id);

        return redirect()->route('billing.index')
            ->with('success', __($payment->status === 'paid'
                ? 'messages.payment_paid'
                : 'messages.payment_pending'));
    }

    /**
     * PG webhook endpoint (CSRF-excluded in bootstrap/app.php). Verifies the
     * webhook signature, then delegates to BillingService::handleWebhook().
     * Idempotency is handled inside handleWebhook via gateway_ref uniqueness.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = config('billing.webhook_secret');

        // ponytail: hash_equals = constant-time; null secret (unset env) fails closed via abort.
        if (! is_string($secret) || ! hash_equals($secret, (string) ($request->header('X-Billing-Signature') ?? ''))) {
            abort(403, 'Invalid webhook signature');
        }

        $payload = $request->validate([
            'order_id' => 'nullable|required_without:id|string',
            'id' => 'nullable|required_without:order_id|string',
            'plan_slug' => 'required|string|exists:plans,slug',
            'amount' => 'nullable|numeric',
            'status' => 'string|in:paid,pending,failed',
        ], [
            'plan_slug.required' => 'Missing plan_slug in webhook payload.',
            'plan_slug.exists' => 'Plan from webhook does not exist.',
        ]);

        $payment = BillingService::handleWebhook($payload);

        if (! $payment) {
            abort(400, 'Unhandled webhook payload.');
        }

        return response()->json(['status' => 'ok', 'payment_id' => $payment->id]);
    }
}
