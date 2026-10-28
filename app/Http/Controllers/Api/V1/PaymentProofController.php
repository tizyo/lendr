<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\PaymentProof;
use App\Services\Payment\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff side of bank-deposit repayments: verify borrower-uploaded slips. */
class PaymentProofController extends BaseApiController
{
    public function __construct(private readonly PaymentProofService $proofs) {}

    /** GET /payment-proofs?status=pending */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', PaymentProof::PENDING);

        $proofs = PaymentProof::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['loan:id,loan_number,outstanding_balance,penalty_balance', 'borrower:id,first_name,last_name,phone', 'reviewer:id,name', 'payment:id,receipt_number'])
            ->latest()
            ->paginate((int) $request->input('per_page', 25));

        return $this->success([
            'data' => collect($proofs->items())->map(fn (PaymentProof $p) => $this->format($p)),
            'meta' => ['total' => $proofs->total(), 'current_page' => $proofs->currentPage(), 'last_page' => $proofs->lastPage()],
            'pending_count' => PaymentProof::pending()->count(),
        ]);
    }

    /** GET /payment-proofs/{proof}/file — the uploaded slip. */
    public function file(PaymentProof $proof): StreamedResponse
    {
        abort_unless(Storage::disk(PaymentProofService::DISK)->exists($proof->file_path), 404);

        return Storage::disk(PaymentProofService::DISK)->response($proof->file_path, $proof->file_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** POST /payment-proofs/{proof}/approve  { amount?, paid_on? } — records the payment. */
    public function approve(Request $request, PaymentProof $proof): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $payment = $this->proofs->approve($proof, $request->user(), isset($data['amount']) ? (float) $data['amount'] : null, $data['paid_on'] ?? null);

        return $this->success([
            'proof' => $this->format($proof->fresh(['loan', 'borrower', 'reviewer', 'payment'])),
            'receipt_number' => $payment->receipt_number,
        ], "Payment recorded — receipt {$payment->receipt_number}.");
    }

    /** POST /payment-proofs/{proof}/reject  { reason } — tells the borrower why. */
    public function reject(Request $request, PaymentProof $proof): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->proofs->reject($proof, $request->user(), $data['reason']);

        return $this->success($this->format($proof->fresh(['loan', 'borrower', 'reviewer'])), 'Proof rejected — the borrower has been told why.');
    }

    private function format(PaymentProof $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status,
            'amount' => (float) $p->amount,
            'paid_on' => $p->paid_on?->toDateString(),
            'bank_reference' => $p->bank_reference,
            'file_name' => $p->file_name,
            'mime_type' => $p->mime_type,
            'file_url' => route('api.v1.payment-proofs.file', $p),
            'loan' => $p->loan ? [
                'id' => $p->loan->id,
                'loan_number' => $p->loan->loan_number,
                'outstanding' => (float) $p->loan->outstanding_balance + (float) $p->loan->penalty_balance,
            ] : null,
            'borrower' => $p->borrower ? [
                'id' => $p->borrower->id,
                'name' => trim($p->borrower->first_name.' '.$p->borrower->last_name),
                'phone' => $p->borrower->phone,
            ] : null,
            'rejection_reason' => $p->rejection_reason,
            'reviewed_by' => $p->reviewer?->name,
            'reviewed_at' => $p->reviewed_at?->toIso8601String(),
            'receipt_number' => $p->payment?->receipt_number,
            'submitted_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
