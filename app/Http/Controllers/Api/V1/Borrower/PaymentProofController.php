<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\PaymentProof;
use App\Services\Payment\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Borrower side of bank-deposit repayments (PWA). */
class PaymentProofController extends BaseApiController
{
    public function __construct(private readonly PaymentProofService $proofs) {}

    /**
     * GET /me/loans/{id}/bank-details
     * Where to pay and which reference to quote.
     */
    public function bankDetails(Request $request, int $id): JsonResponse
    {
        $loan = $this->borrower($request)->loans()->findOrFail($id);

        return $this->success([
            'bank' => $this->proofs->bankDetails(),
            'reference' => $loan->loan_number,
            'outstanding' => (float) $loan->outstanding_balance + (float) $loan->penalty_balance,
        ]);
    }

    /**
     * POST /me/loans/{id}/payment-proofs  (multipart)
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $borrower = $this->borrower($request);
        $loan = $borrower->loans()->findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after:-90 days'],
            'bank_reference' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $proof = $this->proofs->submit($borrower, $loan, $data, $request->file('file'));

        return $this->success($this->format($proof), 'Thanks — we\'ll confirm your deposit once we\'ve checked it with the bank.', 201);
    }

    /**
     * GET /me/payment-proofs — the borrower's submissions and their status.
     */
    public function index(Request $request): JsonResponse
    {
        $proofs = PaymentProof::where('borrower_id', $this->borrower($request)->id)
            ->with('loan:id,loan_number')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (PaymentProof $p) => $this->format($p));

        return $this->success($proofs);
    }

    private function borrower(Request $request): Borrower
    {
        return $request->user();
    }

    private function format(PaymentProof $p): array
    {
        return [
            'id' => $p->id,
            'loan_number' => $p->loan?->loan_number,
            'amount' => (float) $p->amount,
            'paid_on' => $p->paid_on?->toDateString(),
            'bank_reference' => $p->bank_reference,
            'status' => $p->status,
            'rejection_reason' => $p->rejection_reason,
            'submitted_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
