<?php

namespace App\Jobs;

use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\Loan;
use App\Models\Tenant\StandingOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Creates StandingOrder rows for all unpaid future instalments of a loan.
 * Dispatched after disbursement for Enterprise tenants with debit_enabled wallet.
 */
class CreateStandingOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Loan $loan,
        public readonly int  $walletId,
    ) {}

    public function handle(): void
    {
        // TenantWallet is a central-DB model; use explicit connection to avoid tenant context bleeding in sync dispatch
        $wallet = TenantWallet::on(config('tenancy.database.central_connection'))->find($this->walletId);

        if (! $wallet || ! $wallet->is_active || ! $wallet->debit_enabled) {
            return;
        }

        $loan = $this->loan->loadMissing(['schedule', 'borrower:id,phone']);

        $phone = $loan->disbursement_account ?? $loan->borrower?->phone;

        if (! $phone) {
            Log::warning('[StandingOrders] No phone for loan', ['loan_id' => $loan->id]);
            return;
        }

        $created = 0;

        foreach ($loan->schedule as $instalment) {
            if ($instalment->is_paid) {
                continue;
            }

            // Skip if a standing order already exists for this instalment
            $exists = StandingOrder::where('loan_schedule_id', $instalment->id)->exists();
            if ($exists) {
                continue;
            }

            StandingOrder::create([
                'loan_id'         => $loan->id,
                'loan_schedule_id' => $instalment->id,
                'borrower_id'     => $loan->borrower_id,
                'amount'          => $instalment->total_due,
                'phone'           => $phone,
                'gateway'         => $wallet->gateway,
                'due_date'        => $instalment->due_date,
                'status'          => 'pending',
            ]);

            $created++;
        }

        Log::info('[StandingOrders] Created', ['loan_id' => $loan->id, 'count' => $created]);
    }
}
