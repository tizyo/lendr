<?php

namespace App\Jobs;

use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\StandingOrder;
use App\Services\Payment\AutoDebitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes a single standing order — initiates the collection API call.
 * Dispatched by ProcessStandingOrdersCommand for each due order.
 */
class ProcessAutoDebitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // retry logic is handled by StandingOrder::recordFailure()

    public int $timeout = 60;

    public function __construct(
        public readonly StandingOrder $order,
        public readonly int $walletId,
    ) {}

    public function handle(AutoDebitService $service): void
    {
        // TenantWallet is a central-DB model; use explicit connection to avoid tenant context bleeding in sync dispatch
        $wallet = TenantWallet::on(config('tenancy.database.central_connection'))->find($this->walletId);

        if (! $wallet || ! $wallet->is_active || ! $wallet->debit_enabled) {
            $this->order->update(['status' => 'cancelled', 'failure_reason' => 'Wallet inactive or debit disabled.']);

            return;
        }

        // Guard: skip if already completed or cancelled
        if (in_array($this->order->status, ['completed', 'cancelled', 'processing'])) {
            return;
        }

        $service->collect($this->order, $wallet);
    }
}
