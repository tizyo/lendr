<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Services\FundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends BaseApiController
{
    public function __construct(private FundService $fund) {}

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['loan:id,loan_number,borrower_id', 'loan.borrower:id,first_name,last_name', 'recordedBy:id,name'])
            ->when($request->loan_id, fn ($q, $id) => $q->where('loan_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->latest('payment_date')
            ->paginate(20);

        return $this->paginated($payments, fn ($p) => $this->formatPayment($p));
    }

    public function byLoan(Loan $loan): JsonResponse
    {
        $payments = $loan->payments()
            ->with('recordedBy:id,name')
            ->paginate(20);

        return $this->paginated($payments, fn ($p) => $this->formatPayment($p));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank_transfer,airtel_money,mtn_momo,zamtel_kwacha,cheque'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $loan = Loan::findOrFail($request->loan_id);

        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::Defaulted, LoanStatus::Frozen])) {
            return $this->error('Payments can only be recorded for active loans.', 422);
        }

        $payment = DB::transaction(function () use ($request, $loan) {
            $amount = (float) $request->amount;

            // Allocate payment: first to penalty, then interest, then principal
            [$principalAllocated, $interestAllocated, $penaltyAllocated] = $this->allocatePayment($loan, $amount);

            $payment = Payment::create([
                'receipt_number' => $this->generateReceiptNumber(),
                'loan_id' => $loan->id,
                'recorded_by' => auth()->id(),
                'amount' => $amount,
                'principal_allocated' => $principalAllocated,
                'interest_allocated' => $interestAllocated,
                'penalty_allocated' => $penaltyAllocated,
                'fee_allocated' => 0,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'reference' => $request->reference,
                'notes' => $request->notes,
                'source' => 'manual',
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

            // Update schedule instalments
            $this->applyToSchedule($loan, $principalAllocated, $interestAllocated, $request->payment_date);

            // Credit fund: repayment principal + interest
            $repaymentAmount = $principalAllocated + $interestAllocated;
            if ($repaymentAmount > 0) {
                $this->fund->recordRepayment($payment, $repaymentAmount, auth()->id());
            }

            // Credit fund: penalty collected separately
            if ($penaltyAllocated > 0) {
                $this->fund->recordPenalty($payment, $penaltyAllocated, auth()->id());
            }

            return $payment;
        });

        $payment->load(['loan:id,loan_number', 'recordedBy:id,name']);

        return $this->success($this->formatPayment($payment), 'Payment recorded.', 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['loan:id,loan_number,borrower_id', 'loan.borrower:id,first_name,last_name,borrower_number', 'recordedBy:id,name']);

        return $this->success($this->formatPayment($payment, true));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        if (! auth()->user()?->can('payments.delete')) {
            return $this->error('Forbidden.', 403);
        }

        DB::transaction(function () use ($payment) {
            $loan = $payment->loan;

            // Reverse allocation
            $loan->update([
                'total_paid' => max(0, (float) $loan->total_paid - (float) $payment->amount),
                'outstanding_balance' => (float) $loan->outstanding_balance + (float) $payment->principal_allocated + (float) $payment->interest_allocated,
                'penalty_balance' => (float) $loan->penalty_balance + (float) $payment->penalty_allocated,
                'status' => $loan->status === LoanStatus::Completed ? LoanStatus::Active->value : $loan->status->value,
            ]);

            $payment->delete();
        });

        return $this->success(null, 'Payment reversed.');
    }

    /**
     * Generate printable receipt data.
     * GET /api/v1/payments/{payment}/receipt
     */
    public function receipt(Payment $payment): JsonResponse
    {
        $payment->load(['loan.borrower', 'loan.loanType:id,name', 'recordedBy:id,name']);

        return $this->success([
            'receipt_number' => $payment->receipt_number,
            'payment_date' => $payment->payment_date->format('d M Y'),
            'amount' => number_format((float) $payment->amount, 2),
            'payment_method' => $payment->payment_method->label(),
            'reference' => $payment->reference,
            'loan_number' => $payment->loan->loan_number,
            'borrower_name' => $payment->loan->borrower->full_name,
            'borrower_number' => $payment->loan->borrower->borrower_number,
            'loan_type' => $payment->loan->loanType->name,
            'outstanding_after' => number_format((float) $payment->loan->outstanding_balance, 2),
            'recorded_by' => $payment->recordedBy?->name,
            'printed_at' => now()->format('d M Y H:i:s'),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Allocate payment to penalty → interest → principal.
     * Returns [principalAllocated, interestAllocated, penaltyAllocated].
     */
    private function allocatePayment(Loan $loan, float $amount): array
    {
        $remaining = $amount;

        $penaltyAllocated = min($remaining, (float) $loan->penalty_balance);
        $remaining -= $penaltyAllocated;

        // Next: unpaid interest from schedule
        $unpaidInterest = (float) $loan->schedule()
            ->where('is_paid', false)
            ->sum('interest_due') - (float) $loan->schedule()->where('is_paid', false)->sum('interest_paid');

        $interestAllocated = min($remaining, max(0, $unpaidInterest));
        $remaining -= $interestAllocated;

        $principalAllocated = min($remaining, (float) $loan->outstanding_balance - $interestAllocated);
        $principalAllocated = max(0, $principalAllocated);

        return [$principalAllocated, $interestAllocated, $penaltyAllocated];
    }

    /**
     * Apply payment to the earliest unpaid schedule instalments.
     */
    private function applyToSchedule(Loan $loan, float $principalPaid, float $interestPaid, string $paymentDate): void
    {
        $remaining = $principalPaid + $interestPaid;

        $instalments = $loan->schedule()
            ->where('is_paid', false)
            ->orderBy('due_date')
            ->get();

        foreach ($instalments as $instalment) {
            if ($remaining <= 0) {
                break;
            }

            $outstandingDue = (float) $instalment->outstanding;

            if ($remaining >= $outstandingDue) {
                $instalment->update([
                    'total_paid' => (float) $instalment->total_paid + $outstandingDue,
                    'principal_paid' => (float) $instalment->principal_paid + (float) $instalment->principal_due - (float) $instalment->principal_paid,
                    'interest_paid' => (float) $instalment->interest_paid + (float) $instalment->interest_due - (float) $instalment->interest_paid,
                    'outstanding' => 0,
                    'is_paid' => true,
                    'paid_date' => $paymentDate,
                ]);
                $remaining -= $outstandingDue;
            } else {
                $instalment->update([
                    'total_paid' => (float) $instalment->total_paid + $remaining,
                    'outstanding' => max(0, $outstandingDue - $remaining),
                ]);
                $remaining = 0;
            }
        }
    }

    private function generateReceiptNumber(): string
    {
        $prefix = 'REC-'.now()->format('Ym').'-';
        $last = Payment::withTrashed()->where('receipt_number', 'like', $prefix.'%')->max('receipt_number');
        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function formatPayment(Payment $p, bool $full = false): array
    {
        $data = [
            'id' => $p->id,
            'receipt_number' => $p->receipt_number,
            'loan_id' => $p->loan_id,
            'loan_number' => $p->relationLoaded('loan') ? $p->loan->loan_number : null,
            'amount' => (float) $p->amount,
            'principal_allocated' => (float) $p->principal_allocated,
            'interest_allocated' => (float) $p->interest_allocated,
            'penalty_allocated' => (float) $p->penalty_allocated,
            'payment_method' => $p->payment_method->value,
            'payment_method_label' => $p->payment_method->label(),
            'payment_date' => $p->payment_date->toDateString(),
            'reference' => $p->reference,
            'source' => $p->source,
            'recorded_by' => $p->relationLoaded('recordedBy') ? $p->recordedBy?->name : null,
            'created_at' => $p->created_at->format('d M Y H:i'),
        ];

        if ($full && $p->relationLoaded('loan')) {
            $data['borrower'] = $p->loan->relationLoaded('borrower') ? [
                'name' => $p->loan->borrower->full_name,
                'borrower_number' => $p->loan->borrower->borrower_number,
            ] : null;
            $data['notes'] = $p->notes;
        }

        return $data;
    }
}
