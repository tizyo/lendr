<?php

namespace App\Jobs;

use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\Loan;
use App\Services\Payment\AutoDisbursementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Enterprise-only disbursement job that uses TenantWallet credentials.
 * Dispatched from LoanController::disburse() for Enterprise tenants with an active wallet.
 *
 * Supports: flutterwave, airtel_money, mtn_momo, zamtel_kwacha, pawapay
 */
class AutoDisburseLoanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly Loan $loan,
        public readonly int  $walletId,
    ) {}

    public function handle(AutoDisbursementService $service): void
    {
        // TenantWallet is a central-DB model; use explicit connection to avoid tenant context bleeding in sync dispatch
        $wallet = TenantWallet::on(config('tenancy.database.central_connection'))->find($this->walletId);

        if (! $wallet || ! $wallet->is_active || ! $wallet->disburse_enabled) {
            return;
        }

        $service->disburse($this->loan, $wallet);
    }
}
