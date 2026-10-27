<?php

namespace App\Services\Payment;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\Loan;
use App\Services\CrbService;
use App\Services\FundService;
use App\Services\GlLedgerService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What happens after a mobile-money payout attempt.
 *
 * LoanController::disburse() activates the loan and debits the fund up front
 * (reserving the money), but holds back the borrower-facing side effects for
 * mobile-money payouts until the provider answers:
 *
 *   accepted()   → borrower told "funds sent", CRB loan_opened recorded
 *   rejected()   → definitive, no money moved: undo the disbursement (fund,
 *                  GL, schedule, status back to Approved) and alert staff
 *   unconfirmed()→ timeout / provider error, money MAY have moved: change
 *                  nothing, alert staff to verify with the provider
 */
class DisbursementOutcomeService
{
    public const ALERT_ROLES = [UserRole::SuperAdmin->value, UserRole::BranchManager->value];

    public function __construct(
        private readonly FundService $fund,
        private readonly NotificationService $notifications,
        private readonly CrbService $crb,
    ) {}

    public function accepted(Loan $loan, DisbursementLog $log, ?string $providerReference = null, array $response = []): void
    {
        $log->update([
            'status' => 'processing',
            'provider_reference' => $providerReference ?? $log->provider_reference,
            'provider_response' => $response ?: $log->provider_response,
            'failure_reason' => null,
        ]);

        dispatch(new SendLoanEventNotificationJob($loan->id, 'disbursed'));

        $this->recordCrbLoanOpened($loan);

        Log::info('[Disbursement] Payout accepted', ['loan_id' => $loan->id, 'gateway' => $log->gateway, 'ref' => $providerReference]);
    }

    /**
     * The payout definitively failed: put the loan back to Approved so staff
     * can fix the problem and disburse again.
     */
    public function rejected(Loan $loan, DisbursementLog $log, string $reason, ?int $userId = null): void
    {
        $reversed = DB::transaction(function () use ($loan, $log, $reason, $userId) {
            $loan = Loan::lockForUpdate()->find($loan->id);

            // Only an untouched, freshly-activated loan can be unwound safely.
            if (! $loan || $loan->status !== LoanStatus::Active || $loan->payments()->exists()) {
                $log->update(['status' => 'failed', 'failure_reason' => $reason]);

                return false;
            }

            $this->fund->reverseDisbursement($loan, $userId, $reason);
            $this->reverseGl($loan, $userId);

            $loan->schedule()->delete();
            $loan->update([
                'status' => LoanStatus::Approved->value,
                'disbursed_by' => null,
                'disbursement_date' => null,
                'first_repayment_date' => null,
                'maturity_date' => null,
            ]);

            $loan->statusLogs()->create([
                'changed_by' => $userId,
                'from_status' => LoanStatus::Active->value,
                'to_status' => LoanStatus::Approved->value,
                'notes' => "Mobile money payout failed — disbursement reversed. {$reason}",
            ]);

            $log->update(['status' => 'reversed', 'failure_reason' => $reason]);

            return true;
        });

        $this->alertStaff(
            $loan,
            'disbursement_failed',
            "Payout failed for loan {$loan->loan_number}",
            $reversed
                ? "The {$this->gatewayLabel($log)} payout was rejected, so the disbursement was reversed and the loan is back to Approved. Reason: {$reason}"
                : "The {$this->gatewayLabel($log)} payout was rejected, but the loan has changed since, so it was NOT reversed automatically — please review. Reason: {$reason}",
        );

        Log::warning('[Disbursement] Payout rejected', ['loan_id' => $loan->id, 'reversed' => $reversed, 'reason' => $reason]);
    }

    /**
     * We don't know whether the money left (timeout, provider 5xx). Reversing
     * could hand the borrower the money twice if it did go — so leave the loan
     * as disbursed and get a human to check the provider dashboard.
     */
    public function unconfirmed(Loan $loan, DisbursementLog $log, string $reason): void
    {
        $log->update(['status' => 'unconfirmed', 'failure_reason' => $reason]);

        $this->alertStaff(
            $loan,
            'disbursement_unconfirmed',
            "Check payout for loan {$loan->loan_number}",
            "We couldn't confirm the {$this->gatewayLabel($log)} payout of ".($loan->currency ?? 'ZMW').' '.number_format((float) $loan->principal_amount, 2)
            ." ({$reason}). Check the provider dashboard before doing anything — if the money did not leave, reverse the disbursement; do not re-send it.",
        );

        Log::error('[Disbursement] Payout outcome unknown', ['loan_id' => $loan->id, 'reason' => $reason]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** DR Cash on Hand | CR Loans Receivable — mirror of GlLedgerService::postDisbursement(). */
    private function reverseGl(Loan $loan, ?int $userId): void
    {
        try {
            app(GlLedgerService::class)->post(
                "Disbursement reversal: {$loan->loan_number}",
                [
                    ['account_code' => '1001', 'side' => 'debit', 'amount' => $loan->principal_amount],
                    ['account_code' => '1100', 'side' => 'credit', 'amount' => $loan->principal_amount],
                ],
                $loan,
                now()->toDateString(),
                $userId,
            );
        } catch (\Throwable) {
            // Same tolerance as the original posting: GL accounts may not be seeded for this tenant.
        }
    }

    public function recordCrbLoanOpened(Loan $loan): void
    {
        $loan->loadMissing('borrower');

        if (! $identifier = $loan->borrower?->crbIdentifier()) {
            return;
        }

        try {
            $this->crb->recordLoanOpened(
                $this->crb->hash($identifier['value'], $identifier['type']),
                $identifier['type'],
                (string) (tenant('id') ?? 'local'),
                (float) $loan->principal_amount,
                $loan->loan_number,
            );
        } catch (\Throwable) {
            // CRB failure must never block disbursement
        }
    }

    private function alertStaff(Loan $loan, string $type, string $title, string $body): void
    {
        rescue(fn () => $this->notifications->notifyRoles(
            self::ALERT_ROLES,
            $type,
            $title,
            $body,
            ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number],
            'alert-triangle',
        ));
    }

    private function gatewayLabel(DisbursementLog $log): string
    {
        return ucwords(str_replace('_', ' ', $log->gateway));
    }
}
