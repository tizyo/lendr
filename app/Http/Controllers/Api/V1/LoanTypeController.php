<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoanType\StoreLoanTypeRequest;
use App\Http\Requests\Api\V1\LoanType\UpdateLoanTypeRequest;
use App\Models\Tenant\LoanType;
use App\Services\PlanFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LoanTypeController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $withPlans = $request->boolean('with_plans');
        $includeInactive = $request->boolean('include_inactive');
        $cacheKey = 'loan_types_'.tenant('id').($withPlans ? '_with_plans' : '').($includeInactive ? '_all' : '');

        $types = Cache::remember($cacheKey, 3600, function () use ($withPlans, $includeInactive) {
            return LoanType::query()
                ->when($withPlans, fn ($q) => $q->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('name')]))
                ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->withCount('plans')
                ->get()
                ->map(fn ($t) => $this->formatType($t, $withPlans))
                ->values();
        });

        return $this->success($types);
    }

    public function store(StoreLoanTypeRequest $request): JsonResponse
    {
        $svc = PlanFeatureService::forTenant();

        if (! $svc->canAddLoanProduct(LoanType::count())) {
            return $this->error(
                "Your plan allows a maximum of {$svc->limitLabel('max_loan_products')} loan products. Upgrade to add more.",
                422,
            );
        }

        $type = LoanType::create($request->validated());

        $this->forgetLoanTypeCache();

        return $this->success($this->formatType($type->loadCount('plans')), 'Loan type created.', 201);
    }

    public function show(LoanType $loanType): JsonResponse
    {
        $loanType->load(['plans' => fn ($q) => $q->orderBy('name')])->loadCount('plans');

        return $this->success($this->formatType($loanType, true));
    }

    public function update(UpdateLoanTypeRequest $request, LoanType $loanType): JsonResponse
    {
        $loanType->update($request->validated());

        $this->forgetLoanTypeCache();

        return $this->success($this->formatType($loanType->fresh()->loadCount('plans')), 'Loan type updated.');
    }

    public function destroy(LoanType $loanType): JsonResponse
    {
        if ($loanType->loans()->exists()) {
            return $this->error('Cannot delete a loan type that has associated loans.', 422);
        }

        $loanType->delete();

        $this->forgetLoanTypeCache();

        return $this->success(null, 'Loan type deleted.');
    }

    private function forgetLoanTypeCache(): void
    {
        $tenantId = tenant('id');
        Cache::forget("loan_types_{$tenantId}");
        Cache::forget("loan_types_{$tenantId}_with_plans");
        Cache::forget("loan_types_{$tenantId}_all");
        Cache::forget("loan_types_{$tenantId}_with_plans_all");
    }

    private function formatType(LoanType $t, bool $withPlans = false): array
    {
        $data = [
            'id' => $t->id,
            'name' => $t->name,
            'code' => $t->code,
            'description' => $t->description,
            'requires_collateral' => $t->requires_collateral,
            'requires_guarantor' => $t->requires_guarantor,
            'required_documents' => $t->required_documents ?? [],
            'is_active' => $t->is_active,
            'sort_order' => $t->sort_order,
            'plans_count' => $t->plans_count ?? 0,
        ];

        if ($withPlans && $t->relationLoaded('plans')) {
            $data['plans'] = $t->plans->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'interest_rate' => (float) $p->interest_rate,
                'interest_type' => $p->interest_type,
                'interest_period' => $p->interest_period,
                'min_tenure' => $p->min_tenure,
                'max_tenure' => $p->max_tenure,
                'tenure_type' => $p->tenure_type,
                'min_amount' => (float) $p->min_amount,
                'max_amount' => (float) $p->max_amount,
                'repayment_schedule' => $p->repayment_schedule,
                'processing_fee' => (float) $p->processing_fee,
                'insurance_fee' => (float) $p->insurance_fee,
                'penalty_rate' => (float) $p->penalty_rate,
                'grace_period_days' => $p->grace_period_days,
                'is_active' => $p->is_active,
            ])->values();
        }

        return $data;
    }
}
