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
                'id'          => $t->id,
                'name'        => $t->name,
                'description' => $t->description ?? null,
                'plans'       => $t->plans->map(fn ($p) => [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'min_amount'      => (float) $p->min_amount,
                    'max_amount'      => (float) $p->max_amount,
                    'interest_rate'   => (float) $p->interest_rate,
                    'tenure_unit'     => $p->tenure_type,
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
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['nullable', 'string', 'max:100'],
            'phone'            => ['required', 'string', 'max:20'],
            'email'            => ['nullable', 'email'],
            'loan_plan_id'     => ['required', 'integer', 'exists:loan_plans,id'],
            'amount'           => ['required', 'numeric', 'min:1'],
            'tenure'           => ['required', 'integer', 'min:1'],
            'purpose'          => ['nullable', 'string', 'max:500'],
        ]);

        $plan = LoanPlan::with('loanType')->findOrFail($data['loan_plan_id']);

        // Find or create borrower
        $borrower = Borrower::firstOrCreate(
            ['phone' => $data['phone']],
            [
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'] ?? null,
                'email'           => $data['email'] ?? null,
                'borrower_number' => Borrower::generateBorrowerNumber(),
            ]
        );

        $reference = 'LN-EXT-' . strtoupper(Str::random(8));

        $loan = Loan::create([
            'borrower_id'      => $borrower->id,
            'loan_type_id'     => $plan->loan_type_id,
            'loan_plan_id'     => $plan->id,
            'loan_number'      => $reference,
            'principal_amount' => $data['amount'],
            'outstanding_balance' => $data['amount'],
            'interest_rate'    => $plan->interest_rate,
            'interest_type'    => $plan->interest_type ?? 'flat',
            'interest_period'  => $plan->interest_period ?? 'monthly',
            'tenure'              => $data['tenure'],
            'tenure_type'         => $plan->tenure_type ?? 'months',
            'repayment_schedule'  => $plan->repayment_schedule ?? 'monthly',
            'purpose'             => $data['purpose'] ?? null,
            'status'              => 'submitted',
            'application_date'    => now()->toDateString(),
        ]);

        return $this->success([
            'reference'   => $reference,
            'loan_id'     => $loan->id,
            'status'      => $loan->status,
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
            'reference'           => $loan->loan_number,
            'status'              => $loan->status instanceof \BackedEnum ? $loan->status->value : $loan->status,
            'principal_amount'    => (float) $loan->principal_amount,
            'outstanding_balance' => (float) $loan->outstanding_balance,
            'application_date'    => $loan->application_date?->toDateString(),
            'disbursement_date'   => $loan->disbursement_date?->toDateString(),
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
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'phone'          => ['required', 'string'],
            'notes'          => ['nullable', 'string', 'max:300'],
        ]);

        $loan = Loan::where('loan_number', $data['loan_reference'])->first();

        if (! $loan) {
            return $this->error('Loan not found.', 404);
        }

        if (! in_array($loan->status instanceof \BackedEnum ? $loan->status->value : $loan->status, ['active', 'disbursed'])) {
            return $this->error('Loan is not active.', 422);
        }

        $paymentRef = 'PAY-EXT-' . strtoupper(Str::random(8));

        return $this->success([
            'payment_reference' => $paymentRef,
            'loan_reference'    => $data['loan_reference'],
            'amount'            => (float) $data['amount'],
            'status'            => 'pending',
            'message'           => 'Payment initiated. Use the reference to confirm.',
        ], 'Payment initiated.', 201);
    }
}
