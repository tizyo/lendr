<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PlanConfig;
use App\Services\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * GET /billing — tenant subscription overview.
     */
    public function index(): Response
    {
        $tenant       = tenancy()->tenant;
        $subscription = $this->billing->activeSubscription($tenant);
        $invoices     = $this->billing->recentInvoices($tenant);
        $plans        = PlanConfig::allKeyed();

        return Inertia::render('billing/Index', [
            'subscription' => $subscription ? [
                'id'            => $subscription->id,
                'plan'          => $subscription->plan,
                'status'        => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'amount'        => $subscription->amount,
                'currency'      => $subscription->currency,
                'starts_at'     => $subscription->starts_at?->toDateString(),
                'ends_at'       => $subscription->ends_at?->toDateString(),
            ] : null,
            'invoices' => $invoices->map(fn ($inv) => [
                'id'             => $inv->id,
                'plan'           => $inv->plan,
                'amount'         => $inv->amount,
                'currency'       => $inv->currency,
                'billing_cycle'  => $inv->billing_cycle,
                'status'         => $inv->status,
                'gateway'        => $inv->gateway,
                'gateway_tx_ref' => $inv->gateway_tx_ref,
                'paid_at'        => $inv->paid_at?->toDateTimeString(),
                'created_at'     => $inv->created_at?->toDateTimeString(),
            ]),
            'plans' => collect($plans)->map(fn ($p) => [
                'plan'            => $p->plan,
                'label'           => $p->label,
                'price_zmw'       => $p->price_zmw,
                'is_custom_price' => $p->is_custom_price,
            ])->values(),
        ]);
    }

    /**
     * POST /billing/checkout — initiate gateway checkout.
     * Redirects the browser to the hosted payment page.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan'          => ['required', Rule::in(['starter', 'growth', 'enterprise'])],
            'billing_cycle' => ['sometimes', Rule::in(['monthly', 'annual'])],
        ]);

        $tenant       = tenancy()->tenant;
        $billingCycle = $data['billing_cycle'] ?? 'monthly';

        try {
            $url = $this->billing->initiateCheckout($tenant, $data['plan'], $billingCycle);
        } catch (RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        // Hard redirect to gateway (not an Inertia redirect)
        return redirect()->away($url);
    }

    /**
     * GET /billing/callback — return URL from gateway after payment.
     */
    public function callback(Request $request): RedirectResponse
    {
        $transactionId = $request->query('transaction_id', '');
        $txRef         = $request->query('tx_ref', '');
        $status        = $request->query('status', 'failed');

        $result = $this->billing->handleCallback($transactionId, $txRef, $status);

        if ($result['success']) {
            $plan = ucfirst($result['plan'] ?? '');
            return redirect()->route('billing.index')
                ->with('success', "Payment successful! Your workspace has been upgraded to the {$plan} plan.");
        }

        return redirect()->route('billing.index')
            ->with('error', 'Payment could not be verified. ' . ($result['reason'] ?? '') . ' Please contact support if funds were deducted.');
    }
}
