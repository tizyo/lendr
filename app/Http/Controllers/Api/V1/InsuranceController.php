<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\InsuranceClaim;
use App\Models\Tenant\InsuranceProduct;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanInsurance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceController extends BaseApiController
{
    // ─── Insurance Products ───────────────────────────────────────────────────

    public function products(): JsonResponse
    {
        $products = InsuranceProduct::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->formatProduct($p));

        return $this->success($products);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'code'             => ['required', 'string', 'max:30', 'unique:insurance_products,code'],
            'description'      => ['nullable', 'string'],
            'premium_type'     => ['required', 'in:flat,percentage'],
            'premium_rate'     => ['required', 'numeric', 'min:0'],
            'coverage_type'    => ['required', 'in:credit_life,disability,property,comprehensive'],
            'max_term_months'  => ['nullable', 'integer', 'min:1'],
            'notes'            => ['nullable', 'string'],
        ]);

        $product = InsuranceProduct::create($data);

        return $this->success(['product' => $this->formatProduct($product)], 'Insurance product created.', 201);
    }

    public function updateProduct(Request $request, InsuranceProduct $product): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'premium_type'    => ['sometimes', 'in:flat,percentage'],
            'premium_rate'    => ['sometimes', 'numeric', 'min:0'],
            'coverage_type'   => ['sometimes', 'in:credit_life,disability,property,comprehensive'],
            'max_term_months' => ['nullable', 'integer', 'min:1'],
            'is_active'       => ['sometimes', 'boolean'],
            'notes'           => ['nullable', 'string'],
        ]);

        $product->update($data);

        return $this->success(['product' => $this->formatProduct($product->fresh())]);
    }

    public function destroyProduct(InsuranceProduct $product): JsonResponse
    {
        $product->delete();

        return $this->success(null, 'Insurance product deleted.');
    }

    // ─── All Policies & Claims (admin listing) ────────────────────────────────

    public function allPolicies(Request $request): JsonResponse
    {
        $query = LoanInsurance::with('product:id,name,code,coverage_type')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->whereHas('loan', fn ($q) => $q->where('loan_number', 'like', "%{$request->search}%"));
        }

        $policies = $query->paginate(25)->through(fn ($i) => $this->formatPolicy($i));

        return $this->success($policies);
    }

    public function allClaims(Request $request): JsonResponse
    {
        $query = InsuranceClaim::with('policy:id,loan_id,policy_number')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $claims = $query->paginate(25)->through(fn ($c) => $this->formatClaim($c));

        return $this->success($claims);
    }

    // ─── Loan Insurances ──────────────────────────────────────────────────────

    public function loanPolicies(Loan $loan): JsonResponse
    {
        $policies = $loan->insurances()->with('product:id,name,code,coverage_type')->get()
            ->map(fn ($i) => $this->formatPolicy($i));

        return $this->success($policies);
    }

    public function attachPolicy(Request $request, Loan $loan): JsonResponse
    {
        $data = $request->validate([
            'insurance_product_id' => ['required', 'exists:insurance_products,id'],
            'sum_insured'          => ['required', 'numeric', 'min:0'],
            'start_date'           => ['required', 'date'],
            'end_date'             => ['nullable', 'date', 'after:start_date'],
            'notes'                => ['nullable', 'string'],
        ]);

        $product = InsuranceProduct::findOrFail($data['insurance_product_id']);

        $policy = $loan->insurances()->create([
            'insurance_product_id' => $product->id,
            'recorded_by'          => $request->user()->id,
            'policy_number'        => LoanInsurance::generatePolicyNumber(),
            'sum_insured'          => $data['sum_insured'],
            'premium_amount'       => $product->calculatePremium((float) $loan->principal_amount),
            'start_date'           => $data['start_date'],
            'end_date'             => $data['end_date'] ?? null,
            'notes'                => $data['notes'] ?? null,
            'status'               => 'active',
        ]);

        return $this->success(
            ['policy' => $this->formatPolicy($policy->load('product:id,name,code,coverage_type'))],
            'Insurance policy attached.',
            201
        );
    }

    public function updatePolicy(Request $request, LoanInsurance $policy): JsonResponse
    {
        $data = $request->validate([
            'status'   => ['sometimes', 'in:active,lapsed,cancelled,claimed'],
            'end_date' => ['nullable', 'date'],
            'notes'    => ['nullable', 'string'],
        ]);

        $policy->update($data);

        return $this->success(['policy' => $this->formatPolicy($policy->fresh()->load('product:id,name,code,coverage_type'))]);
    }

    // ─── Claims ───────────────────────────────────────────────────────────────

    public function fileClaim(Request $request, LoanInsurance $policy): JsonResponse
    {
        $data = $request->validate([
            'claim_type'    => ['required', 'in:death,disability,property_damage,other'],
            'claim_amount'  => ['required', 'numeric', 'min:1'],
            'incident_date' => ['required', 'date'],
            'description'   => ['nullable', 'string'],
        ]);

        $claim = InsuranceClaim::create([
            'loan_insurance_id' => $policy->id,
            'recorded_by'       => $request->user()->id,
            'claim_number'      => InsuranceClaim::generateClaimNumber(),
            'claim_type'        => $data['claim_type'],
            'claim_amount'      => $data['claim_amount'],
            'incident_date'     => $data['incident_date'],
            'description'       => $data['description'] ?? null,
            'status'            => 'pending',
        ]);

        return $this->success(['claim' => $this->formatClaim($claim)], 'Claim filed.', 201);
    }

    public function reviewClaim(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $data = $request->validate([
            'status'           => ['required', 'in:approved,paid,rejected,under_review'],
            'approved_amount'  => ['nullable', 'numeric', 'min:0'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $claim->update(array_merge($data, [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]));

        return $this->success(['claim' => $this->formatClaim($claim->fresh())]);
    }

    public function policyClaims(LoanInsurance $policy): JsonResponse
    {
        $claims = $policy->claims()->orderByDesc('id')->get()
            ->map(fn ($c) => $this->formatClaim($c));

        return $this->success($claims);
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatProduct(InsuranceProduct $p): array
    {
        return [
            'id'              => $p->id,
            'name'            => $p->name,
            'code'            => $p->code,
            'description'     => $p->description,
            'premium_type'    => $p->premium_type,
            'premium_rate'    => (float) $p->premium_rate,
            'coverage_type'   => $p->coverage_type,
            'max_term_months' => $p->max_term_months,
            'is_active'       => $p->is_active,
            'notes'           => $p->notes,
        ];
    }

    private function formatPolicy(LoanInsurance $i): array
    {
        return [
            'id'             => $i->id,
            'loan_id'        => $i->loan_id,
            'policy_number'  => $i->policy_number,
            'product'        => $i->relationLoaded('product') ? [
                'id'            => $i->product->id,
                'name'          => $i->product->name,
                'code'          => $i->product->code,
                'coverage_type' => $i->product->coverage_type,
            ] : null,
            'sum_insured'    => (float) $i->sum_insured,
            'premium_amount' => (float) $i->premium_amount,
            'start_date'     => $i->start_date?->toDateString(),
            'end_date'       => $i->end_date?->toDateString(),
            'status'         => $i->status,
            'notes'          => $i->notes,
            'created_at'     => $i->created_at?->toDateString(),
        ];
    }

    private function formatClaim(InsuranceClaim $c): array
    {
        return [
            'id'                => $c->id,
            'loan_insurance_id' => $c->loan_insurance_id,
            'claim_number'      => $c->claim_number,
            'claim_type'        => $c->claim_type,
            'claim_amount'      => (float) $c->claim_amount,
            'approved_amount'   => $c->approved_amount ? (float) $c->approved_amount : null,
            'status'            => $c->status,
            'incident_date'     => $c->incident_date?->toDateString(),
            'description'       => $c->description,
            'rejection_reason'  => $c->rejection_reason,
            'reviewed_at'       => $c->reviewed_at?->toDateTimeString(),
            'created_at'        => $c->created_at?->toDateString(),
        ];
    }
}
