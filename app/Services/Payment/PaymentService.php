<?php

namespace App\Services\Payment;

use App\Enums\LoanStatus;
use App\Jobs\RecalculateCreditScoreJob;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\Payment;
use App\Services\CrbService;
use App\Services\FundService;
use App\Services\GlLedgerService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Encapsulates the payment-recording business logic so that both
 * PaymentController (manual) and webhook handlers can share it.
 */
class PaymentService
{
    public function __construct(
        private FundService $fund,
        private NotificationService $notifications,
        private CrbService $crb,
    ) {}

    /**
     * Record a payment against a loan.
     *
     * @param  array{
     *   amount: float,
     *   payment_method: string,
     *   payment_date: string,
     *   source: string,
     *   reference?: ?string,
     *   momo_transaction_id?: ?string,
     *   momo_provider?: ?string,
     *   notes?: ?string,
     *   recorded_by?: ?int,
     * } $attributes
     */
    public function record(Loan $loan, array $attributes): Payment
    {
        return DB::transaction(function () use ($loan, $attributes) {
            // Lock the loan row for the duration of this transaction so a
            // concurrent payment (e.g. a retried webhook) can't read a stale
            // balance and race this one — without this, two payments landing
            // at the same time can both compute against the pre-payment
            // balance and the second update silently clobbers the first.
            $loan = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            $amount = (float) $attributes['amount'];

            [$principalAllocated, $interestAllocated, $penaltyAllocated] = $this->allocate($loan, $amount);

            $payment = Payment::create([
                'receipt_number' => $this->generateReceiptNumber(),
                'loan_id' => $loan->id,
                'recorded_by' => $attributes['recorded_by'] ?? null,
                'amount' => $amount,
                'principal_allocated' => $principalAllocated,
                'interest_allocated' => $interestAllocated,
                'penalty_allocated' => $penaltyAllocated,
                'fee_allocated' => 0,
                'payment_method' => $attributes['payment_method'],
                'payment_date' => $attributes['payment_date'],
                'reference' => $attributes['reference'] ?? null,
                'momo_transaction_id' => $attributes['momo_transaction_id'] ?? null,
                'momo_provider' => $attributes['momo_provider'] ?? null,
                'source' => $attributes['source'],
                'notes' => $attributes['notes'] ?? null,
                'is_overdue_payment' => $loan->isOverdue(),
            ]);

            // Update loan balances
            $newTotalPaid = (float) $loan->total_paid + $amount;
            $newOutstanding = max(0, (float) $loan->outstanding_balance - ($principalAllocated + $interestAllocated));
            $newPenalty = max(0, (float) $loan->penalty_balance - $penaltyAllocated);

            $newStatus = $loan->status;
            if ($newOutstanding <= 0 && $newPenalty <= 0) {
                $newStatus = LoanStatus::Completed;
                $loan->update(['closed_date' => now()->toDateString()]);
            }

            $loan->update([
                'total_paid' => $newTotalPaid,
                'outstanding_balance' => $newOutstanding,
                'penalty_balance' => $newPenalty,
                'status' => $newStatus->value,
            ]);

            $this->applyToSchedule($loan, $principalAllocated, $interestAllocated, $attributes['payment_date']);

            try {
                app(GlLedgerService::class)->postPayment($payment);
            } catch (\Throwable) {
                // GL accounts may not be seeded for this tenant; do not block the payment
            }

            $repaymentAmount = $principalAllocated + $interestAllocated;
            if ($repaymentAmount > 0) {
                $this->fund->recordRepayment($payment, $repaymentAmount, $attributes['recorded_by'] ?? null);
            }
            if ($penaltyAllocated > 0) {
                $this->fund->recordPenalty($payment, $penaltyAllocated, $attributes['recorded_by'] ?? null);
            }

            dispatch(new RecalculateCreditScoreJob($loan->borrower_id));
            dispatch(new SendLoanEventNotificationJob($loan->id, 'payment_received', [
                'amount_paid' => number_format($amount, 2),
            ]));

            // Notify the loan officer who created the loan
            if ($loan->created_by) {
                $this->notifications->notifyUser(
                    $loan->created_by,
                    'payment_received',
                    "Payment received on {$loan->loan_number}",
                    'ZMW '.number_format($amount, 2).' received. Remaining: ZMW '.number_format(max(0, (float) $loan->outstanding_balance - ($principalAllocated + $interestAllocated)), 2),
                    ['loan_id' => $loan->id, 'payment_id' => $payment->id],
                );
            }

            // CRB score event — fire outside transaction to avoid deadlock; catch all errors
            try {
                $borrower = $loan->borrower;
                $identifier = $borrower->crbIdentifier();
                if ($identifier) {
                    $hash = $this->crb->hash($identifier['value'], $identifier['type']);
                    $tenantId = (string) (tenant('id') ?? 'local');
                    $loanCompleted = ($newStatus === LoanStatus::Completed);
                    $dpd = $this->computeDpd($loan, $attributes['payment_date']);
                    $isEarly = $dpd === 0 && ! $loan->isOverdue();

                    $this->crb->recordPayment(
                        $hash,
                        $identifier['type'],
                        $tenantId,
                        $loan->loan_number,
                        $dpd,
                        $isEarly,
                        $loanCompleted,
                        $amount,
                    );
                }
            } catch (\Throwable) {
                // CRB errors must never fail a payment
            }

            return $payment;
        });
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function allocate(Loan $loan, float $amount): array
    {
        $remaining = $amount;

        $penaltyAllocated = min($remaining, (float) $loan->penalty_balance);
        $remaining -= $penaltyAllocated;

        $unpaidInterest = (float) $loan->schedule()
            ->where('is_paid', false)->sum('interest_due')
            - (float) $loan->schedule()->where('is_paid', false)->sum('interest_paid');

        $interestAllocated = min($remaining, max(0, $unpaidInterest));
        $remaining -= $interestAllocated;

        $principalAllocated = max(0, min($remaining, (float) $loan->outstanding_balance - $interestAllocated));

        return [$principalAllocated, $interestAllocated, $penaltyAllocated];
    }

    private function applyToSchedule(Loan $loan, float $principalPaid, float $interestPaid, string $paymentDate): void
    {
        $remaining = $principalPaid + $interestPaid;

        foreach ($loan->schedule()->where('is_paid', false)->orderBy('due_date')->get() as $inst) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) $inst->outstanding;

            if ($remaining >= $due) {
                $inst->update([
                    'total_paid' => (float) $inst->total_paid + $due,
                    'principal_paid' => (float) $inst->principal_due,
                    'interest_paid' => (float) $inst->interest_due,
                    'outstanding' => 0,
                    'is_paid' => true,
                    'paid_date' => $paymentDate,
                ]);
                $remaining -= $due;
            } else {
                $inst->update([
                    'total_paid' => (float) $inst->total_paid + $remaining,
                    'outstanding' => max(0, $due - $remaining),
                ]);
                $remaining = 0;
            }
        }
    }

    /**
     * Calculate DPD (days past due) at time of payment.
     * Returns 0 if no overdue instalments exist.
     */
    private function computeDpd(Loan $loan, string $paymentDate): int
    {
        $oldest = LoanSchedule::where('loan_id', $loan->id)
            ->where('is_paid', false)
            ->whereDate('due_date', '<', $paymentDate)
            ->orderBy('due_date')
            ->value('due_date');

        if (! $oldest) {
            return 0;
        }

        return (int) $oldest->diffInDays(\Carbon\Carbon::parse($paymentDate));
    }

    private function generateReceiptNumber(): string
    {
        $prefix = 'REC-'.now()->format('Ym').'-';
        $last = Payment::withTrashed()->where('receipt_number', 'like', $prefix.'%')->max('receipt_number');
        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
