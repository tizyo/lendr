<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LoanActionException;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseApiController
{
    public function __construct(private PaymentService $payments) {}

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

    /**
     * Record a payment taken by staff. The same posting as every other channel
     * (PaymentService): allocation by instalment, schedule, fund, ledger, CRB.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank_transfer,airtel_money,mtn_momo,zamtel_kwacha,cheque'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->payments->record(Loan::findOrFail($data['loan_id']), [
                ...$data,
                'recorded_by' => auth()->id(),
                'source' => 'manual',
            ]);
        } catch (LoanActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        $payment->load(['loan:id,loan_number', 'recordedBy:id,name']);

        return $this->success($this->formatPayment($payment), 'Payment recorded.', 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['loan:id,loan_number,borrower_id', 'loan.borrower:id,first_name,last_name,borrower_number', 'recordedBy:id,name']);

        return $this->success($this->formatPayment($payment, true));
    }

    /**
     * Reverse a payment: schedule, loan balances, fund and ledger are all undone
     * (PaymentService::reverse). The payment stays on record as reversed.
     */
    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            $this->payments->reverse($payment, auth()->id(), $data['reason']);
        } catch (LoanActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

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
            'fee_allocated' => (float) $p->fee_allocated,
            'penalty_allocated' => (float) $p->penalty_allocated,
            'unallocated_amount' => (float) $p->unallocated_amount,
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
