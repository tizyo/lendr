<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OpenBankingController extends BaseApiController
{
    /**
     * GET /api/v1/open/products
     * List available loan products.
     */
    public function products(): JsonResponse
    {
        $types = LoanType::where('is_active', true)
            ->with(['plans' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description ?? null,
                'plans' => $t->plans->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'min_amount' => (float) $p->min_amount,
                    'max_amount' => (float) $p->max_amount,
                    'interest_rate' => (float) $p->interest_rate,
                    'tenure_unit' => $p->tenure_type,
                ]),
            ]);

        return $this->success(['products' => $types]);
    }

    /**
     * POST /api/v1/open/loan/apply
     * Submit a loan application.
     */
    public function applyLoan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'loan_plan_id' => ['required', 'integer', 'exists:loan_plans,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'tenure' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = LoanPlan::with('loanType')->findOrFail($data['loan_plan_id']);

        // Same limits as every other channel: an active plan, its amount and tenure ranges.
        if (! $plan->is_active || ! $plan->loanType?->is_active) {
            return $this->error('That loan plan is not available.', 422);
        }
        if ((float) $data['amount'] < (float) $plan->min_amount || (float) $data['amount'] > (float) $plan->max_amount) {
            return $this->error("Amount must be between K {$plan->min_amount} and K {$plan->max_amount}.", 422);
        }
        if ($data['tenure'] < $plan->min_tenure || $data['tenure'] > $plan->max_tenure) {
            return $this->error("Tenure must be between {$plan->min_tenure} and {$plan->max_tenure} {$plan->tenure_type}.", 422);
        }

        // Find or create borrower
        $borrower = Borrower::firstOrCreate(
            ['phone' => $data['phone']],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'borrower_number' => Borrower::generateBorrowerNumber(),
            ],
        );

        if ($borrower->is_blacklisted || $borrower->is_active === false) {
            return $this->error('This applicant cannot be offered a loan.', 422);
        }

        // Interest and fees worked out like any application, so the loan owes what the plan says.
        $amounts = app(\App\Services\LoanCalculatorService::class)->calculateAmounts($plan, (float) $data['amount'], (int) $data['tenure']);
        $reference = 'LN-EXT-'.strtoupper(Str::random(8));

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'loan_type_id' => $plan->loan_type_id,
            'loan_plan_id' => $plan->id,
            'loan_number' => $reference,
            'principal_amount' => $data['amount'],
            'interest_amount' => $amounts['interest_amount'],
            'processing_fee' => $amounts['processing_fee'],
            'insurance_fee' => $amounts['insurance_fee'],
            'total_payable' => $amounts['total_payable'],
            'outstanding_balance' => $amounts['total_payable'],
            'interest_rate' => $plan->interest_rate,
            'interest_type' => $plan->interest_type ?? 'flat',
            'interest_period' => $plan->interest_period ?? 'monthly',
            'tenure' => $data['tenure'],
            'tenure_type' => $plan->tenure_type ?? 'months',
            'repayment_schedule' => $plan->repayment_schedule ?? 'monthly',
            'penalty_rate' => $plan->penalty_rate,
            'grace_period_days' => $plan->grace_period_days,
            'loan_purpose' => $data['purpose'] ?? null,
            'status' => 'submitted',
            'application_date' => now()->toDateString(),
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
        ]);

        $loan->statusLogs()->create([
            'changed_by' => null,
            'from_status' => null,
            'to_status' => 'submitted',
            'notes' => 'Submitted through the open API ('.($request->attributes->get('api_client')?->name ?? 'API client').').',
        ]);

        return $this->success([
            'reference' => $reference,
            'loan_id' => $loan->id,
            'status' => $loan->status,
            'borrower_id' => $borrower->id,
        ], 'Loan application submitted successfully.', 201);
    }

    /**
     * GET /api/v1/open/loan/{reference}/status
     * Check the status of a loan by reference number.
     */
    public function loanStatus(string $reference): JsonResponse
    {
        $loan = Loan::where('loan_number', $reference)
            ->select(['id', 'loan_number', 'status', 'principal_amount', 'outstanding_balance', 'application_date', 'disbursement_date'])
            ->first();

        if (! $loan) {
            return $this->error('Loan not found.', 404);
        }

        return $this->success([
            'reference' => $loan->loan_number,
            'status' => $loan->status instanceof \BackedEnum ? $loan->status->value : $loan->status,
            'principal_amount' => (float) $loan->principal_amount,
            'outstanding_balance' => (float) $loan->outstanding_balance,
            'application_date' => $loan->application_date?->toDateString(),
            'disbursement_date' => $loan->disbursement_date?->toDateString(),
        ]);
    }

    /**
     * POST /api/v1/open/payment/initiate
     * Initiate a payment for a loan.
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_reference' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'phone' => ['required', 'string', 'max:20'],
            'provider' => ['required', 'in:airtel_money,mtn_momo,zamtel_kwacha,lipila,pawapay'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $loan = Loan::where('loan_number', $data['loan_reference'])->first();

        if (! $loan) {
            return $this->error('Loan not found.', 404);
        }

        if (! in_array($loan->status, \App\Services\Payment\PaymentService::PAYABLE_STATUSES, true)) {
            return $this->error('Loan is not accepting repayments.', 422);
        }

        $owed = (float) $loan->outstanding_balance + (float) $loan->penalty_balance;
        if ((float) $data['amount'] > $owed) {
            return $this->error('Amount is more than the K '.number_format($owed, 2).' owed on this loan.', 422);
        }

        // A real collection request, like the borrower app's: the payment is posted
        // when the provider's callback confirms it.
        $intent = \App\Models\Tenant\MobileMoneyIntent::create([
            'loan_id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'reference' => 'PAY-EXT-'.strtoupper(Str::random(10)),
            'provider' => $data['provider'],
            'phone' => $data['phone'],
            'amount' => $data['amount'],
            'currency' => $loan->currency ?? 'ZMW',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);

        dispatch(new \App\Jobs\InitiateMobileMoneyPaymentJob($intent));

        return $this->success([
            'payment_reference' => $intent->reference,
            'loan_reference' => $data['loan_reference'],
            'amount' => (float) $intent->amount,
            'status' => 'pending',
            'message' => 'Payment request sent to the payer\'s phone. Check the loan status for the result.',
        ], 'Payment initiated.', 201);
    }
}
