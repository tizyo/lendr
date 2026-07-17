<?php

namespace App\Services\Billing;

use App\Models\Landlord\PlanConfig;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionInvoice;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Str;

class BillingService
{
    public function __construct(private readonly BillingGatewayManager $gateways) {}

    /**
     * Initiate a hosted checkout for the given tenant + plan.
     *
     * Creates a pending SubscriptionInvoice, calls the active gateway,
     * and returns the redirect URL.
     */
    public function initiateCheckout(Tenant $tenant, string $plan, string $billingCycle = 'monthly'): string
    {
        $planConfig = PlanConfig::forPlan($plan);
        $amount     = (float) ($planConfig?->price_zmw ?? 0);
        $currency   = $tenant->currency ?? 'ZMW';
        $txRef      = 'LENDR-SUB-' . Str::uuid();

        $gateway = $this->gateways->active();

        $invoice = SubscriptionInvoice::create([
            'tenant_id'     => $tenant->id,
            'gateway'       => $gateway->getName(),
            'gateway_tx_ref'=> $txRef,
            'plan'          => $plan,
            'amount'        => $amount,
            'currency'      => $currency,
            'billing_cycle' => $billingCycle,
            'status'        => 'pending',
        ]);

        $redirectUrl = route('billing.callback');

        return $gateway->initiatePayment([
            'tx_ref'       => $txRef,
            'amount'       => $amount,
            'currency'     => $currency,
            'redirect_url' => $redirectUrl,
            'customer'     => [
                'email'        => $tenant->admin_email ?? '',
                'name'         => $tenant->name,
                'phone_number' => '',
            ],
            'meta' => [
                'tenant_id'  => $tenant->id,
                'plan'       => $plan,
                'invoice_id' => $invoice->id,
            ],
            'customizations' => [
                'title'       => 'LENDR Subscription',
                'description' => ($planConfig?->label ?? ucfirst($plan)) . ' Plan — ' . ucfirst($billingCycle),
                'logo'        => '',
            ],
        ]);
    }

    /**
     * Handle the redirect callback from the gateway.
     * Returns ['success' => bool, 'plan' => string|null].
     */
    public function handleCallback(string $transactionId, string $txRef, string $gatewayStatus): array
    {
        $invoice = SubscriptionInvoice::where('gateway_tx_ref', $txRef)->first();

        if (! $invoice) {
            return ['success' => false, 'reason' => 'Invoice not found.'];
        }

        if ($invoice->status === 'paid') {
            return ['success' => true, 'plan' => $invoice->plan]; // already processed
        }

        // Optimistic check — if gateway says failed, mark and bail
        $rawStatus = strtolower($gatewayStatus);
        if (! in_array($rawStatus, ['successful', 'success', 'completed'])) {
            $invoice->update(['status' => 'failed', 'gateway_tx_id' => $transactionId ?: null]);
            return ['success' => false, 'reason' => 'Payment was not successful.'];
        }

        // Verify with gateway API
        try {
            $gateway  = $this->gateways->driver($invoice->gateway);
            $verified = $gateway->verifyPayment($transactionId);
        } catch (\Throwable $e) {
            $invoice->update(['status' => 'failed']);
            return ['success' => false, 'reason' => 'Gateway verification failed: ' . $e->getMessage()];
        }

        if ($verified['status'] !== 'success') {
            $invoice->update(['status' => 'failed', 'gateway_tx_id' => $transactionId]);
            return ['success' => false, 'reason' => 'Gateway returned non-success status.'];
        }

        return $this->activateSubscription($invoice, $transactionId, $verified['amount']);
    }

    /**
     * Handle a webhook notification (idempotent — safe to call multiple times).
     */
    public function handleWebhook(string $txRef, string $transactionId, string $status, float $amount): array
    {
        $invoice = SubscriptionInvoice::where('gateway_tx_ref', $txRef)->first();

        if (! $invoice) {
            return ['handled' => false, 'reason' => 'Invoice not found.'];
        }

        if ($invoice->status === 'paid') {
            return ['handled' => true, 'reason' => 'Already processed.'];
        }

        if ($status !== 'success') {
            $invoice->update(['status' => 'failed', 'gateway_tx_id' => $transactionId]);
            return ['handled' => true, 'reason' => 'Marked as failed.'];
        }

        $result = $this->activateSubscription($invoice, $transactionId, $amount);
        return ['handled' => true, ...$result];
    }

    /**
     * Retrieve the active subscription for a tenant (or null).
     */
    public function activeSubscription(Tenant $tenant): ?Subscription
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    /**
     * Get recent invoices for a tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection<SubscriptionInvoice>
     */
    public function recentInvoices(Tenant $tenant, int $limit = 10)
    {
        return SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function activateSubscription(SubscriptionInvoice $invoice, string $gatewayTxId, float $amount): array
    {
        // Mark invoice paid
        $invoice->update([
            'status'        => 'paid',
            'gateway_tx_id' => $gatewayTxId,
            'paid_at'       => now(),
        ]);

        // Determine subscription period
        $startsAt = now();
        $endsAt   = $invoice->billing_cycle === 'annual'
            ? $startsAt->copy()->addYear()
            : $startsAt->copy()->addMonth();

        // Deactivate any existing active subscription for this tenant
        Subscription::where('tenant_id', $invoice->tenant_id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Create new active subscription
        $subscription = Subscription::create([
            'tenant_id'      => $invoice->tenant_id,
            'plan'           => $invoice->plan,
            'status'         => 'active',
            'gateway'        => $invoice->gateway,
            'gateway_tx_ref' => $invoice->gateway_tx_ref,
            'amount'         => $amount,
            'currency'       => $invoice->currency,
            'billing_cycle'  => $invoice->billing_cycle,
            'starts_at'      => $startsAt,
            'ends_at'        => $endsAt,
        ]);

        // Link invoice to subscription
        $invoice->update(['subscription_id' => $subscription->id]);

        // Activate the tenant
        $tenant = Tenant::find($invoice->tenant_id);
        if ($tenant) {
            $tenant->update([
                'plan'          => $invoice->plan,
                'status'        => 'active',
                'trial_ends_at' => null,
            ]);
        }

        return ['success' => true, 'plan' => $invoice->plan];
    }
}
