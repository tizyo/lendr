<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\RiskFlag;
use App\Models\Tenant\RiskPolicy;
use App\Services\RiskAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskPolicyController extends BaseApiController
{
    // GET /api/v1/risk-policies
    public function index(): JsonResponse
    {
        $policies = RiskPolicy::orderBy('sort_order')->get();

        return $this->success($policies);
    }

    // POST /api/v1/risk-policies
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'rule_type'   => 'required|in:'.implode(',', RiskPolicy::ruleTypes()),
            'operator'    => 'nullable|string|max:20',
            'value'       => 'required|string',
            'action'      => 'required|in:warn,block',
            'is_active'   => 'boolean',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $policy = RiskPolicy::create($data);

        return $this->success(['policy' => $policy], 'Policy created.', 201);
    }

    // GET /api/v1/risk-policies/{policy}
    public function show(RiskPolicy $riskPolicy): JsonResponse
    {
        return $this->success($riskPolicy->load('flags'));
    }

    // PUT /api/v1/risk-policies/{policy}
    public function update(Request $request, RiskPolicy $riskPolicy): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'rule_type'   => 'sometimes|in:'.implode(',', RiskPolicy::ruleTypes()),
            'operator'    => 'nullable|string|max:20',
            'value'       => 'sometimes|string',
            'action'      => 'sometimes|in:warn,block',
            'is_active'   => 'boolean',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $riskPolicy->update($data);

        return $this->success(['policy' => $riskPolicy->fresh()]);
    }

    // DELETE /api/v1/risk-policies/{policy}
    public function destroy(RiskPolicy $riskPolicy): JsonResponse
    {
        $riskPolicy->delete();

        return $this->success(null, 'Deleted.', 204);
    }

    // POST /api/v1/loans/{loan}/risk-assess
    public function assess(Loan $loan): JsonResponse
    {
        $result = app(RiskAssessmentService::class)->assess($loan);

        return $this->success($result);
    }

    // GET /api/v1/loans/{loan}/risk-flags
    public function flags(Loan $loan): JsonResponse
    {
        $flags = RiskFlag::where('loan_id', $loan->id)
            ->with(['policy:id,name,action', 'overriddenBy:id,name'])
            ->get();

        return $this->success($flags);
    }

    // POST /api/v1/risk-flags/{flag}/override
    public function override(Request $request, RiskFlag $riskFlag): JsonResponse
    {
        if ($riskFlag->overridden) {
            return $this->error('Flag is already overridden.', 422);
        }

        $data = $request->validate([
            'override_reason' => 'required|string|max:500',
        ]);

        $riskFlag->update([
            'overridden'      => true,
            'overridden_by'   => auth()->id(),
            'override_reason' => $data['override_reason'],
            'overridden_at'   => now(),
        ]);

        return $this->success(['flag' => $riskFlag->fresh(['overriddenBy:id,name'])]);
    }

    public function ruleTypes(): JsonResponse
    {
        return $this->success(RiskPolicy::ruleTypes());
    }
}
