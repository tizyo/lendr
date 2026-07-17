<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\LoanPlan;
use App\Services\LoanCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Loan amortization preview — no loan record is created.
 * Accessible to authenticated staff and borrowers.
 */
class LoanCalculatorController extends BaseApiController
{
    public function __construct(private LoanCalculatorService $calc) {}

    /**
     * POST /calculator/calculate
     *
     * Two modes:
     *   1. plan_id + principal + tenure  → use existing LoanPlan config
     *   2. Raw parameters (no plan_id)   → calculate from scratch
     */
    public function calculate(Request $request): JsonResponse
    {
        if ($request->has('plan_id')) {
            return $this->calculateFromPlan($request);
        }

        return $this->calculateRaw($request);
    }

    private function calculateFromPlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'    => ['required', 'integer', 'exists:loan_plans,id'],
            'principal'  => ['required', 'numeric', 'min:1'],
            'tenure'     => ['required', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
        ]);

        $plan   = LoanPlan::findOrFail($data['plan_id']);
        $result = $this->calc->calculate(
            $plan,
            (float) $data['principal'],
            (int)   $data['tenure'],
            $data['start_date'] ?? now()->toDateString()
        );

        return $this->success($result);
    }

    private function calculateRaw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'principal'          => ['required', 'numeric', 'min:1'],
            'interest_rate'      => ['required', 'numeric', 'min:0', 'max:1000'],
            'interest_type'      => ['required', 'string', 'in:flat,reducing_balance,compound'],
            'interest_period'    => ['required', 'string', 'in:daily,weekly,monthly,annually'],
            'tenure'             => ['required', 'integer', 'min:1'],
            'tenure_type'        => ['required', 'string', 'in:days,weeks,months'],
            'repayment_schedule' => ['required', 'string', 'in:daily,weekly,bi_weekly,monthly,bullet,quarterly'],
            'processing_fee'     => ['sometimes', 'numeric', 'min:0'],
            'insurance_fee'      => ['sometimes', 'numeric', 'min:0'],
            'start_date'         => ['sometimes', 'date'],
        ]);

        $result = $this->calc->preview($data);

        return $this->success($result);
    }
}
