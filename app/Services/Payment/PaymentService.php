<?php

namespace App\Services\Payment;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\LoanActionException;
use App\Jobs\RecalculateCreditScoreJob;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentAllocation;
use App\Services\CrbService;
use App\Services\FundService;
use App\Services\GlLedgerService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place money is posted to a loan, and taken back off it. Staff entry,
 * batch upload, field collection, bank-deposit proofs, mobile-money webhooks and
 * standing orders all come through here, so every payment gets the same checks,
 * allocation, schedule update, fund and ledger entries, and can be reversed exactly.
 *
 * Allocation: penalties first, then instalment by instalment (oldest first):
 * interest, fees, principal. What each instalment received is kept in
 * payment_allocations, so a reversal restores the schedule precisely.
 *
 * Amounts use BCMath at 2 decimal places.
 */
class PaymentService
{
    public const PAYABLE_STATUSES = [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::Defaulted, LoanStatus::Frozen];

    public function __construct(
        private FundService $fund,
        private NotificationService $notifications,
        private CrbService $crb,
    ) {}

    /**
     * Record a payment against a loan.
     *
     * $allowOverpayment: money already received (webhook, bank deposit, debit order)
     * is kept even if it's more than the loan owes; the excess is held as a customer
     * credit (GL 2100). Staff entry refuses an overpayment instead.
     *
     * @param  array{
     *   amount: float|string,
     *   payment_method: string|PaymentMethod,
     *   payment_date: string,
     *   source: string,
     *   reference?: ?string,
     *   momo_transaction_id?: ?string,
     *   momo_provider?: ?string,
     *   notes?: ?string,
     *   recorded_by?: ?int,
     * } $attributes
     *
     * @throws LoanActionException when the payment can't be recorded
     */
    public function record(Loan $loan, array $attributes, bool $allowOverpayment = false): Payment
    {
        $method = $attributes['payment_method'] instanceof PaymentMethod
            ? $attributes['payment_method']
            : PaymentMethod::tryFrom((string) ($attributes['payment_method'] ?? ''));

        if (! $method) {
            throw LoanActionException::invalid('"'.($attributes['payment_method'] ?? '').'" isn\'t a payment method LENDR recognises.');
        }

        $amount = $this->money($attributes['amount'] ?? 0);
        if (bccomp($amount, '0', 2) <= 0) {
            throw LoanActionException::invalid('The amount must be more than zero.');
        }

        try {
            $date = Carbon::parse($attributes['payment_date'] ?? now())->startOfDay();
        } catch (\Throwable) {
            throw LoanActionException::invalid('"'.$attributes['payment_date'].'" isn\'t a valid payment date.');
        }
        if ($date->isAfter(now()->endOfDay())) {
            throw LoanActionException::invalid('The payment date can\'t be in the future.');
        }

        [$payment, $completed, $loan] = DB::transaction(function () use ($loan, $attributes, $amount, $method, $date, $allowOverpayment) {
            // Locked for the whole posting: two payments (a retried webhook, two
            // cashiers) can't both read the same balance.
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($loan->status, self::PAYABLE_STATUSES, true)) {
                throw LoanActionException::invalid("Payments can only be recorded for running loans; {$loan->loan_number} is {$loan->status->label()}.");
            }
            if ($loan->disbursement_date && $date->lt($loan->disbursement_date->copy()->startOfDay())) {
                throw LoanActionException::invalid('The payment date is before the loan was disbursed ('.$loan->disbursement_date->format('d M Y').').');
            }
            $this->assertNotDuplicate($loan, $attributes);

            $penaltyDue = $this->money($loan->penalty_balance);
            $outstanding = $this->money($loan->outstanding_balance);
            $payable = bcadd($penaltyDue, $outstanding, 2);

            if (! $allowOverpayment && bccomp($amount, $payable, 2) > 0) {
                throw LoanActionException::invalid("K {$this->fmt($amount)} is more than the K {$this->fmt($payable)} owed on {$loan->loan_number} (balance plus penalties).");
            }

            $remaining = $amount;

            $penalty = $this->min($remaining, $penaltyDue);
            $remaining = bcsub($remaining, $penalty, 2);

            $interest = $fee = $principal = '0.00';
            $allocations = [];

            $instalments = LoanSchedule::where('loan_id', $loan->id)
                ->where('is_paid', false)
                ->orderBy('due_date')->orderBy('instalment_number')
                ->lockForUpdate()
                ->get();

            foreach ($instalments as $inst) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }

                $i = $this->min($remaining, $this->left($inst->interest_due, $inst->interest_paid));
                $remaining = bcsub($remaining, $i, 2);
                $f = $this->min($remaining, $this->left($inst->fee_due, $inst->fee_paid));
                $remaining = bcsub($remaining, $f, 2);
                $p = $this->min($remaining, $this->left($inst->principal_due, $inst->principal_paid));
                $remaining = bcsub($remaining, $p, 2);

                $applied = bcadd(bcadd($i, $f, 2), $p, 2);
                if (bccomp($applied, '0', 2) <= 0) {
                    continue;
                }

                $totalPaid = bcadd($this->money($inst->total_paid), $applied, 2);
                $left = $this->max('0', bcsub($this->money($inst->total_due), $totalPaid, 2));
                $settled = bccomp($left, '0', 2) <= 0;

                $inst->update([
                    'interest_paid' => bcadd($this->money($inst->interest_paid), $i, 2),
                    'fee_paid' => bcadd($this->money($inst->fee_paid), $f, 2),
                    'principal_paid' => bcadd($this->money($inst->principal_paid), $p, 2),
                    'total_paid' => $totalPaid,
                    'outstanding' => $left,
                    'is_paid' => $settled,
                    'paid_date' => $settled ? $date->toDateString() : $inst->paid_date,
                ]);

                $allocations[] = ['loan_schedule_id' => $inst->id, 'interest' => $i, 'fee' => $f, 'principal' => $p];
                $interest = bcadd($interest, $i, 2);
                $fee = bcadd($fee, $f, 2);
                $principal = bcadd($principal, $p, 2);
            }

            // Balance the schedule doesn't carry (a loan without schedule rows) is principal.
            $unscheduled = $this->max('0', bcsub($outstanding, bcadd(bcadd($interest, $fee, 2), $principal, 2), 2));
            $extra = $this->min($remaining, $unscheduled);
            $principal = bcadd($principal, $extra, 2);
            $remaining = bcsub($remaining, $extra, 2);

            $unallocated = $remaining; // only ever > 0 when an overpayment is allowed
            $applied = bcadd(bcadd($interest, $fee, 2), $principal, 2);

            $payment = Payment::create([
                'receipt_number' => $this->generateReceiptNumber(),
                'loan_id' => $loan->id,
                'recorded_by' => $attributes['recorded_by'] ?? null,
                'amount' => $amount,
                'principal_allocated' => $principal,
                'interest_allocated' => $interest,
                'penalty_allocated' => $penalty,
                'fee_allocated' => $fee,
                'unallocated_amount' => $unallocated,
                'payment_method' => $method->value,
                'payment_date' => $date->toDateString(),
                'reference' => $attributes['reference'] ?? null,
                'momo_transaction_id' => $attributes['momo_transaction_id'] ?? null,
                'momo_provider' => $attributes['momo_provider'] ?? null,
                'source' => $attributes['source'] ?? 'manual',
                'notes' => $attributes['notes'] ?? null,
                'is_overdue_payment' => $loan->isOverdue(),
            ]);

            foreach ($allocations as $row) {
                PaymentAllocation::create(['payment_id' => $payment->id, ...$row]);
            }

            $newOutstanding = $this->max('0', bcsub($outstanding, $applied, 2));
            $newPenalty = $this->max('0', bcsub($penaltyDue, $penalty, 2));
            $completed = bccomp($newOutstanding, '0', 2) <= 0 && bccomp($newPenalty, '0', 2) <= 0;
            $fromStatus = $loan->status->value;

            $loan->update([
                'total_paid' => bcadd($this->money($loan->total_paid), bcsub($amount, $unallocated, 2), 2),
                'outstanding_balance' => $newOutstanding,
                'penalty_balance' => $newPenalty,
                'status' => $completed ? LoanStatus::Completed->value : $loan->status->value,
                'closed_date' => $completed ? $date->toDateString() : $loan->closed_date,
            ]);

            if ($completed) {
                $loan->statusLogs()->create([
                    'changed_by' => $attributes['recorded_by'] ?? null,
                    'from_status' => $fromStatus,
                    'to_status' => LoanStatus::Completed->value,
                    'notes' => "Fully repaid ({$payment->receipt_number}).",
                ]);
            }

            app(GlLedgerService::class)->postPayment($payment);

            $toFund = bcadd($applied, $unallocated, 2);
            if (bccomp($toFund, '0', 2) > 0) {
                $this->fund->recordRepayment($payment, (float) $toFund, $attributes['recorded_by'] ?? null);
            }
            if (bccomp($penalty, '0', 2) > 0) {
                $this->fund->recordPenalty($payment, (float) $penalty, $attributes['recorded_by'] ?? null);
            }

            return [$payment, $completed, $loan];
        });

        $this->afterRecorded($payment, $loan, $completed);

        return $payment;
    }

    /**
     * Undo a payment completely: the instalments it paid, the loan's balances (a
     * completed loan reopens), the fund, and the ledger (a reversing entry). The
     * payment stays on record, marked reversed with who, when and why.
     *
     * @throws LoanActionException
     */
    public function reverse(Payment $payment, ?int $byUserId, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $byUserId, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (! $payment || $payment->reversed_at) {
                throw LoanActionException::invalid('This payment has already been reversed.');
            }

            $loan = Loan::whereKey($payment->loan_id)->lockForUpdate()->firstOrFail();

            $allocations = PaymentAllocation::where('payment_id', $payment->id)->get();
            $applied = bcadd(bcadd($this->money($payment->principal_allocated), $this->money($payment->interest_allocated), 2), $this->money($payment->fee_allocated), 2);

            if ($allocations->isNotEmpty()) {
                foreach ($allocations as $row) {
                    $inst = LoanSchedule::whereKey($row->loan_schedule_id)->lockForUpdate()->first();
                    if (! $inst) {
                        continue;
                    }
                    $this->takeBack($inst, $this->money($row->interest), $this->money($row->fee), $this->money($row->principal));
                }
            } else {
                // Recorded before allocations were kept: unwind from the latest paid instalments.
                $remaining = $applied;
                $paid = LoanSchedule::where('loan_id', $loan->id)->where('total_paid', '>', 0)
                    ->orderByDesc('due_date')->orderByDesc('instalment_number')->lockForUpdate()->get();

                foreach ($paid as $inst) {
                    if (bccomp($remaining, '0', 2) <= 0) {
                        break;
                    }
                    $p = $this->min($remaining, $this->money($inst->principal_paid));
                    $remaining = bcsub($remaining, $p, 2);
                    $f = $this->min($remaining, $this->money($inst->fee_paid));
                    $remaining = bcsub($remaining, $f, 2);
                    $i = $this->min($remaining, $this->money($inst->interest_paid));
                    $remaining = bcsub($remaining, $i, 2);
                    // Older partial payments only moved total_paid; take any rest from there.
                    $rest = $this->min($remaining, bcsub($this->money($inst->total_paid), bcadd(bcadd($p, $f, 2), $i, 2), 2));
                    $remaining = bcsub($remaining, $rest, 2);

                    $this->takeBack($inst, $i, $f, bcadd($p, $rest, 2), plainTotal: $rest);
                }
            }

            $wasCompleted = $loan->status === LoanStatus::Completed;
            $fromStatus = $loan->status->value;
            $loan->update([
                'total_paid' => $this->max('0', bcsub($this->money($loan->total_paid), bcsub($this->money($payment->amount), $this->money($payment->unallocated_amount), 2), 2)),
                'outstanding_balance' => bcadd($this->money($loan->outstanding_balance), $applied, 2),
                'penalty_balance' => bcadd($this->money($loan->penalty_balance), $this->money($payment->penalty_allocated), 2),
                'status' => $wasCompleted ? LoanStatus::Active->value : $loan->status->value,
                'closed_date' => $wasCompleted ? null : $loan->closed_date,
            ]);

            $loan->statusLogs()->create([
                'changed_by' => $byUserId,
                'from_status' => $fromStatus,
                'to_status' => $loan->status->value,
                'notes' => "Payment {$payment->receipt_number} (K {$this->fmt($payment->amount)}) reversed: {$reason}",
            ]);

            $fromFund = bcadd($applied, $this->money($payment->unallocated_amount), 2);
            if (bccomp($fromFund, '0', 2) > 0) {
                $this->fund->reverseRepayment($payment, (float) $fromFund, $byUserId);
            }
            if (bccomp($this->money($payment->penalty_allocated), '0', 2) > 0) {
                $this->fund->reversePenalty($payment, (float) $payment->penalty_allocated, $byUserId);
            }

            app(GlLedgerService::class)->reverseEntriesFor($payment, "Reversal of payment {$payment->receipt_number}: {$reason}");

            $payment->update(['reversed_at' => now(), 'reversed_by' => $byUserId, 'reversal_reason' => $reason]);
            $payment->delete();

            dispatch(new RecalculateCreditScoreJob($loan->borrower_id))->afterCommit();

            return $payment;
        });
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function afterRecorded(Payment $payment, Loan $loan, bool $completed): void
    {
        dispatch(new RecalculateCreditScoreJob($loan->borrower_id));
        dispatch(new SendLoanEventNotificationJob($loan->id, 'payment_received', [
            'amount_paid' => $this->fmt($payment->amount),
        ]));

        if ($loan->created_by) {
            $this->notifications->notifyUser(
                $loan->created_by,
                'payment_received',
                "Payment received on {$loan->loan_number}",
                'ZMW '.$this->fmt($payment->amount).' received. Remaining: ZMW '.$this->fmt($loan->outstanding_balance),
                ['loan_id' => $loan->id, 'payment_id' => $payment->id],
            );
        }

        // CRB errors must never fail a payment.
        try {
            $identifier = $loan->borrower?->crbIdentifier();
            if ($identifier) {
                $dpd = $this->computeDpd($loan, $payment->payment_date->toDateString());
                $this->crb->recordPayment(
                    $this->crb->hash($identifier['value'], $identifier['type']),
                    $identifier['type'],
                    (string) (tenant('id') ?? 'local'),
                    $loan->loan_number,
                    $dpd,
                    $dpd === 0 && ! $loan->isOverdue(),
                    $completed,
                    (float) $payment->amount,
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function takeBack(LoanSchedule $inst, string $interest, string $fee, string $principal, string $plainTotal = '0'): void
    {
        $total = bcadd(bcadd($interest, $fee, 2), $principal, 2);
        $totalPaid = $this->max('0', bcsub($this->money($inst->total_paid), $total, 2));
        $principalBack = bcsub($principal, $plainTotal, 2);

        $inst->update([
            'interest_paid' => $this->max('0', bcsub($this->money($inst->interest_paid), $interest, 2)),
            'fee_paid' => $this->max('0', bcsub($this->money($inst->fee_paid), $fee, 2)),
            'principal_paid' => $this->max('0', bcsub($this->money($inst->principal_paid), $principalBack, 2)),
            'total_paid' => $totalPaid,
            'outstanding' => $this->max('0', bcsub($this->money($inst->total_due), $totalPaid, 2)),
            'is_paid' => false,
            'paid_date' => null,
        ]);
    }

    /** The same bank or mobile-money reference can't be posted to a loan twice. */
    private function assertNotDuplicate(Loan $loan, array $attributes): void
    {
        foreach (['reference', 'momo_transaction_id'] as $field) {
            $value = trim((string) ($attributes[$field] ?? ''));
            if ($value !== '' && Payment::where('loan_id', $loan->id)->where($field, $value)->exists()) {
                throw LoanActionException::invalid("A payment with reference \"{$value}\" is already recorded on {$loan->loan_number}.");
            }
        }
    }

    /** Days past due at the time of payment (0 when nothing was overdue). */
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

        return (int) abs(Carbon::parse($oldest)->diffInDays(Carbon::parse($paymentDate)));
    }

    private function generateReceiptNumber(): string
    {
        $prefix = 'REC-'.now()->format('Ym').'-';
        $last = Payment::withTrashed()->where('receipt_number', 'like', $prefix.'%')->max('receipt_number');
        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function money(mixed $value): string
    {
        return bcadd(is_numeric($value) ? (string) $value : '0', '0', 2);
    }

    private function left(mixed $due, mixed $paid): string
    {
        return $this->max('0', bcsub($this->money($due), $this->money($paid), 2));
    }

    private function min(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }

    private function max(string $a, string $b): string
    {
        return bccomp($a, $b, 2) >= 0 ? bcadd($a, '0', 2) : bcadd($b, '0', 2);
    }

    private function fmt(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
