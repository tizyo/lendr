<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Enums\LoanStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Jobs\InitiateMobileMoneyPaymentJob;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\MobileMoneyIntent;
use App\Models\Tenant\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileController extends BaseApiController
{
    /**
     * GET /api/v1/me — Return the authenticated borrower's profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();
        $borrower->loadCount(['loans', 'activeLoans']);

        return $this->success([
            'id' => $borrower->id,
            'borrower_number' => $borrower->borrower_number,
            'full_name' => $borrower->full_name,
            'first_name' => $borrower->first_name,
            'last_name' => $borrower->last_name,
            'phone' => $borrower->phone,
            'email' => $borrower->email,
            'avatar' => $borrower->avatar,
            'is_active' => $borrower->is_active,
            'kyc_verified' => $borrower->kyc_verified,
            'loans_count' => $borrower->loans_count,
            'active_loans_count' => $borrower->active_loans_count,
            'total_borrowed' => (string) $borrower->total_borrowed,
            'outstanding_balance' => (string) $borrower->outstanding_balance,
        ], 'OK');
    }

    /**
     * PUT /api/v1/me/profile — Update borrower profile from KYC wizard.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'employer' => ['nullable', 'string', 'max:150'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $borrower->update($data);

        return $this->success(null, 'Profile updated successfully.');
    }

    /**
     * GET /api/v1/me/loans — Borrower's own loan history.
     */
    public function loans(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $loans = $borrower->loans()
            ->with('loanType:id,name', 'loanPlan:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return $this->paginated($loans, fn ($l) => [
            'id' => $l->id,
            'loan_number' => $l->loan_number,
            'type' => $l->loanType?->name,
            'principal' => (string) $l->principal_amount,
            'outstanding' => (string) $l->outstanding_balance,
            'status' => $l->status->value,
            'status_label' => $l->status->label(),
            'application_date' => $l->application_date?->toDateString(),
            'disbursed_at' => $l->disbursed_at?->toDateString(),
        ]);
    }

    /**
     * GET /api/v1/me/payments — Borrower's payment history.
     */
    public function payments(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $loanIds = $borrower->loans()->pluck('id');

        $payments = Payment::whereIn('loan_id', $loanIds)
            ->with('loan:id,loan_number')
            ->latest('payment_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($payments, fn ($p) => [
            'id' => $p->id,
            'receipt_number' => $p->receipt_number,
            'amount' => (string) $p->amount,
            'payment_method' => $p->payment_method instanceof \App\Enums\PaymentMethod
                ? $p->payment_method->label()
                : $p->payment_method,
            'loan_number' => $p->loan?->loan_number,
            'payment_date' => $p->payment_date?->toDateString(),
            'reference' => $p->reference,
        ]);
    }

    /**
     * GET /api/v1/me/credit-score — Borrower's credit score.
     */
    public function creditScore(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        return $this->success([
            'score' => $borrower->credit_score,
            'band' => $this->scoreBand($borrower->credit_score),
            'updated_at' => null, // Populated when credit scoring engine (P6) is built
        ], 'OK');
    }

    /**
     * GET /api/v1/me/payments/{payment}/receipt
     * Returns receipt data for a payment that belongs to the authenticated borrower.
     */
    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        // Scope to borrower's own loans
        $loanIds = $borrower->loans()->pluck('id');
        if (! $loanIds->contains($payment->loan_id)) {
            return $this->error('Payment not found.', 404);
        }

        $payment->load(['loan.borrower', 'loan.loanType:id,name', 'recordedBy:id,name']);

        return $this->success([
            'receipt_number' => $payment->receipt_number,
            'payment_date' => $payment->payment_date->format('d M Y'),
            'amount' => number_format((float) $payment->amount, 2),
            'payment_method' => $payment->payment_method instanceof \App\Enums\PaymentMethod
                ? $payment->payment_method->label()
                : $payment->payment_method,
            'reference' => $payment->reference,
            'loan_number' => $payment->loan->loan_number,
            'borrower_name' => $payment->loan->borrower->full_name,
            'borrower_number' => $payment->loan->borrower->borrower_number,
            'loan_type' => $payment->loan->loanType?->name,
            'principal_paid' => number_format((float) $payment->principal_allocated, 2),
            'interest_paid' => number_format((float) $payment->interest_allocated, 2),
            'penalty_paid' => number_format((float) $payment->penalty_allocated, 2),
            'outstanding_after' => number_format((float) $payment->loan->outstanding_balance, 2),
            'printed_at' => now()->format('d M Y H:i:s'),
        ]);
    }

    /**
     * POST /api/v1/me/payments/initiate
     * Initiate a mobile money self-service payment.
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $data = $request->validate([
            'loan_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:1'],
            'phone' => ['required', 'string', 'max:20'],
            'provider' => ['required', 'in:airtel_money,mtn_momo,zamtel_kwacha'],
        ]);

        // Confirm loan belongs to borrower
        $loan = $borrower->loans()->find($data['loan_id']);
        if (! $loan) {
            return $this->error('Loan not found.', 404);
        }

        if (! in_array($loan->status->value, [LoanStatus::Active->value, LoanStatus::Disbursed->value])) {
            return $this->error('This loan is not eligible for payment.', 422);
        }

        if ((float) $data['amount'] > (float) $loan->outstanding_balance) {
            return $this->error('Amount exceeds outstanding balance.', 422);
        }

        // Check for duplicate pending intent on the same loan
        $existing = MobileMoneyIntent::where('loan_id', $loan->id)
            ->where('borrower_id', $borrower->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $this->success([
                'reference' => $existing->reference,
                'status' => 'pending',
                'expires_at' => $existing->expires_at?->toDateTimeString(),
                'message' => 'A pending payment request already exists. Complete it on your phone.',
            ], 'Existing payment intent found.');
        }

        $reference = 'LENDR-'.strtoupper(Str::random(10));

        $intent = MobileMoneyIntent::create([
            'loan_id' => $loan->id,
            'borrower_id' => $borrower->id,
            'reference' => $reference,
            'provider' => $data['provider'],
            'phone' => $data['phone'],
            'amount' => $data['amount'],
            'currency' => 'ZMW',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        dispatch(new InitiateMobileMoneyPaymentJob($intent));

        return $this->success([
            'reference' => $intent->reference,
            'status' => 'pending',
            'provider' => $intent->provider,
            'amount' => number_format((float) $intent->amount, 2),
            'phone' => $intent->phone,
            'expires_at' => $intent->expires_at->toDateTimeString(),
            'message' => 'A payment prompt has been sent to your phone. Please approve it within 30 minutes.',
        ], 'Payment initiated.', 202);
    }

    private function scoreBand(?int $score): string
    {
        if (! $score) {
            return 'unrated';
        }
        if ($score < 550) {
            return 'poor';
        }
        if ($score < 650) {
            return 'fair';
        }
        if ($score < 750) {
            return 'good';
        }

        return 'excellent';
    }
}
