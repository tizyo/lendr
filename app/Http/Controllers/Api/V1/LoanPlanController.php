<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoanPlan\StoreLoanPlanRequest;
use App\Http\Requests\Api\V1\LoanPlan\UpdateLoanPlanRequest;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Services\LoanCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanPlanController extends BaseApiController
{
    public function __construct(private LoanCalculatorService $calculator) {}

    public function index(Request $request): JsonResponse
    {
        $plans = LoanPlan::query()
            ->with('loanType:id,name,code')
            ->when($request->loan_type_id, fn ($q, $id) => $q->where('loan_type_id', $id))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->formatPlan($p));

        return $this->success($plans);
    }

    public function store(StoreLoanPlanRequest $request): JsonResponse
    {
        $plan = LoanPlan::create($request->validated());
        $plan->load('loanType:id,name,code');

        return $this->success($this->formatPlan($plan), 'Loan plan created.', 201);
    }

    public function show(LoanPlan $loanPlan): JsonResponse
    {
        $loanPlan->load('loanType:id,name,code');

        return $this->success($this->formatPlan($loanPlan));
    }

    public function update(UpdateLoanPlanRequest $request, LoanPlan $loanPlan): JsonResponse
    {
        $loanPlan->update($request->validated());
        $loanPlan->refresh()->load('loanType:id,name,code');

        return $this->success($this->formatPlan($loanPlan), 'Loan plan updated.');
    }

    public function destroy(LoanPlan $loanPlan): JsonResponse
    {
        if ($loanPlan->loans()->exists()) {
            return $this->error('Cannot delete a plan that has associated loans.', 422);
        }

        $loanPlan->delete();

        return $this->success(null, 'Loan plan deleted.');
    }

    /**
     * Calculate loan amounts and schedule preview for a given plan + principal + tenure.
     * GET /api/v1/loan-plans/{plan}/calculate?principal=5000&tenure=12&disbursement_date=2026-03-15
     */
    public function calculate(Request $request, LoanPlan $loanPlan): JsonResponse
    {
        $request->validate([
            'principal'         => ['required', 'numeric', 'min:0.01'],
            'tenure'            => ['required', 'integer', 'min:1'],
            'disbursement_date' => ['required', 'date'],
        ]);

        $principal = (float) $request->principal;
        $tenure    = (int) $request->tenure;

        if ($principal < $loanPlan->min_amount || $principal > $loanPlan->max_amount) {
            return $this->error("Principal must be between {$loanPlan->min_amount} and {$loanPlan->max_amount}.", 422);
        }

        if ($tenure < $loanPlan->min_tenure || $tenure > $loanPlan->max_tenure) {
            return $this->error("Tenure must be between {$loanPlan->min_tenure} and {$loanPlan->max_tenure} {$loanPlan->tenure_type}.", 422);
        }

        $calc = $this->calculator->calculate($loanPlan, $principal, $tenure, $request->disbursement_date);

        return $this->success($calc);
    }

    private function formatPlan(LoanPlan $p): array
    {
        return [
            'id'                 => $p->id,
            'loan_type_id'       => $p->loan_type_id,
            'loan_type'          => $p->relationLoaded('loanType') ? ['id' => $p->loanType->id, 'name' => $p->loanType->name] : null,
            'name'               => $p->name,
            'code'               => $p->code,
            'interest_rate'      => (float) $p->interest_rate,
            'interest_type'      => $p->interest_type,
            'interest_period'    => $p->interest_period,
            'min_tenure'         => $p->min_tenure,
            'max_tenure'         => $p->max_tenure,
            'tenure_type'        => $p->tenure_type,
            'min_amount'         => (float) $p->min_amount,
            'max_amount'         => (float) $p->max_amount,
            'penalty_rate'       => (float) $p->penalty_rate,
            'penalty_type'       => $p->penalty_type,
            'grace_period_days'  => $p->grace_period_days,
            'repayment_schedule' => $p->repayment_schedule,
            'processing_fee'     => (float) $p->processing_fee,
            'insurance_fee'      => (float) $p->insurance_fee,
            'is_active'          => $p->is_active,
        ];
    }
}
