<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\AutoDecision;
use App\Models\Tenant\AutoDecisionRule;
use App\Models\Tenant\Loan;
use App\Services\AutoDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoDecisionController extends BaseApiController
{
    public function __construct(private AutoDecisionService $service) {}

    /**
     * GET /api/v1/auto-decision/rules
     */
    public function rules(): JsonResponse
    {
        $rules = AutoDecisionRule::orderBy('priority')->get();

        return $this->success(['rules' => $rules->map(fn ($r) => $this->formatRule($r))]);
    }

    /**
     * POST /api/v1/auto-decision/rules
     */
    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'product_type'       => ['nullable', 'string'],
            'min_credit_score'   => ['nullable', 'numeric', 'min:0', 'max:850'],
            'max_dti_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_income'         => ['nullable', 'numeric', 'min:0'],
            'max_loan_amount'    => ['nullable', 'numeric', 'min:0'],
            'min_tenure_months'  => ['nullable', 'integer', 'min:1'],
            'max_tenure_months'  => ['nullable', 'integer', 'min:1'],
            'action'             => ['required', 'in:approve,decline,manual'],
            'priority'           => ['nullable', 'integer', 'min:1'],
            'is_active'          => ['nullable', 'boolean'],
        ]);

        $rule = AutoDecisionRule::create($data);

        return $this->success(['rule' => $this->formatRule($rule)], 'Rule created.', 201);
    }

    /**
     * PUT /api/v1/auto-decision/rules/{rule}
     */
    public function updateRule(Request $request, AutoDecisionRule $rule): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:100'],
            'min_credit_score'   => ['sometimes', 'numeric', 'min:0', 'max:850'],
            'max_dti_pct'        => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'max_loan_amount'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'min_tenure_months'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_tenure_months'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'action'             => ['sometimes', 'in:approve,decline,manual'],
            'priority'           => ['sometimes', 'integer', 'min:1'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        $rule->update($data);

        return $this->success(['rule' => $this->formatRule($rule->fresh())], 'Rule updated.');
    }

    /**
     * DELETE /api/v1/auto-decision/rules/{rule}
     */
    public function destroyRule(AutoDecisionRule $rule): JsonResponse
    {
        $rule->delete();

        return $this->success([], 'Rule deleted.');
    }

    /**
     * POST /api/v1/auto-decision/evaluate/{loan}
     * Run the engine against an existing loan application.
     */
    public function evaluate(Loan $loan): JsonResponse
    {
        $decision = $this->service->evaluate($loan);

        return $this->success(['decision' => $this->formatDecision($decision)], 'Decision evaluated.');
    }

    /**
     * GET /api/v1/auto-decision/{loan}
     * Retrieve the latest auto-decision for a loan.
     */
    public function show(Loan $loan): JsonResponse
    {
        $decision = AutoDecision::where('loan_id', $loan->id)->latest()->first();

        if (! $decision) {
            return $this->error('No auto-decision found for this loan.', 404);
        }

        return $this->success(['decision' => $this->formatDecision($decision)]);
    }

    /**
     * POST /api/v1/auto-decision/{decision}/override
     * Staff manual override of an auto-decision.
     */
    public function override(Request $request, AutoDecision $decision): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,decline,manual'],
            'notes'  => ['nullable', 'string'],
        ]);

        $decision->update([
            'action'      => $data['action'],
            'notes'       => $data['notes'] ?? $decision->notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $this->success(['decision' => $this->formatDecision($decision->fresh())], 'Decision overridden.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatRule(AutoDecisionRule $r): array
    {
        return [
            'id'                 => $r->id,
            'name'               => $r->name,
            'product_type'       => $r->product_type,
            'min_credit_score'   => (float) $r->min_credit_score,
            'max_dti_pct'        => $r->max_dti_pct ? (float) $r->max_dti_pct : null,
            'min_income'         => $r->min_income ? (float) $r->min_income : null,
            'max_loan_amount'    => $r->max_loan_amount ? (float) $r->max_loan_amount : null,
            'min_tenure_months'  => $r->min_tenure_months,
            'max_tenure_months'  => $r->max_tenure_months,
            'action'             => $r->action,
            'priority'           => $r->priority,
            'is_active'          => $r->is_active,
        ];
    }

    private function formatDecision(AutoDecision $d): array
    {
        return [
            'id'           => $d->id,
            'loan_id'      => $d->loan_id,
            'rule_id'      => $d->rule_id,
            'action'       => $d->action,
            'credit_score' => $d->credit_score ? (float) $d->credit_score : null,
            'dti_pct'      => $d->dti_pct ? (float) $d->dti_pct : null,
            'factors'      => $d->factors ?? [],
            'notes'        => $d->notes,
            'reviewed_by'  => $d->reviewed_by,
            'reviewed_at'  => $d->reviewed_at?->toDateTimeString(),
        ];
    }
}
