<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Landlord\PublicLoanProduct;
use App\Models\Tenant\LoanType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-tenant public loan product marketplace.
 *
 * Tenants publish their loan products to the central marketplace.
 * Borrowers (or anonymous users) browse all active listings.
 */
class PublicMarketplaceController extends BaseApiController
{
    // ── Tenant-facing: manage their own listings ─────────────────────────────

    /**
     * GET /marketplace/products
     * List all active products across all tenants (for borrowers to browse).
     */
    public function browse(Request $request): JsonResponse
    {
        $query = PublicLoanProduct::active()
            ->when($request->q, fn ($q) => $q->where(fn ($q) => $q->where('product_name', 'like', "%{$request->q}%")
                ->orWhere('description', 'like', "%{$request->q}%")
                ->orWhere('tenant_name', 'like', "%{$request->q}%"),
            ))
            ->when($request->max_amount, fn ($q) => $q->where('min_amount', '<=', $request->max_amount))
            ->when($request->min_amount, fn ($q) => $q->where('max_amount', '>=', $request->min_amount))
            ->when($request->tenure_type, fn ($q) => $q->where('tenure_type', $request->tenure_type))
            ->when($request->schedule, fn ($q) => $q->where('repayment_schedule', $request->schedule))
            ->orderByDesc('applications_count')
            ->orderBy('interest_rate');

        $products = $query->paginate(20);

        return $this->paginated($products, fn ($p) => $this->formatProduct($p));
    }

    /**
     * GET /marketplace/products/{id}
     * Single product detail.
     */
    public function show(int $id): JsonResponse
    {
        $product = PublicLoanProduct::active()->findOrFail($id);

        return $this->success($this->formatProduct($product));
    }

    /**
     * POST /marketplace/products
     * Tenant publishes a loan product to the marketplace.
     */
    public function publish(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_type_id' => ['required', 'integer', 'exists:loan_types,id'],
            'product_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'tenant_city' => ['nullable', 'string', 'max:100'],
        ]);

        $loanType = LoanType::with('plans')->findOrFail($data['loan_type_id']);
        $plan = $loanType->plans()->active()->first();
        $tenantId = (string) (tenant('id') ?? 'local');

        if (! $plan) {
            return $this->error('Loan type has no active plan to publish.', 422);
        }

        // Upsert — one listing per loan type per tenant
        $product = PublicLoanProduct::updateOrCreate(
            ['tenant_id' => $tenantId, 'product_code' => (string) $loanType->id],
            [
                'tenant_name' => $data['product_name'],   // display name for the MFI's product
                'tenant_city' => $data['tenant_city'] ?? null,
                'product_name' => $data['product_name'],
                'description' => $data['description'] ?? null,
                'min_amount' => $plan->min_amount,
                'max_amount' => $plan->max_amount,
                'interest_rate' => $plan->interest_rate,
                'interest_type' => $plan->interest_type,
                'interest_period' => $plan->interest_period,
                'min_tenure' => $plan->min_tenure,
                'max_tenure' => $plan->max_tenure,
                'tenure_type' => $plan->tenure_type,
                'repayment_schedule' => $plan->getRawOriginal('repayment_schedule'),
                'processing_fee' => $plan->processing_fee,
                'requires_collateral' => $loanType->requires_collateral,
                'requires_guarantor' => $loanType->requires_guarantor,
                'is_active' => true,
            ],
        );

        return $this->success($this->formatProduct($product), 'Product published to marketplace.', 201);
    }

    /**
     * DELETE /marketplace/products/{id}
     * Tenant removes (deactivates) their listing.
     */
    public function unpublish(int $id): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        $product = PublicLoanProduct::where('tenant_id', $tenantId)->findOrFail($id);
        $product->update(['is_active' => false]);

        return $this->success(null, 'Product removed from marketplace.');
    }

    /**
     * GET /marketplace/my-products
     * Products this tenant has published.
     */
    public function myProducts(Request $request): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        $products = PublicLoanProduct::where('tenant_id', $tenantId)->get();

        return $this->success($products->map(fn ($p) => $this->formatProduct($p))->values());
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function formatProduct(PublicLoanProduct $p): array
    {
        return [
            'id' => $p->id,
            'tenant_name' => $p->tenant_name,
            'tenant_city' => $p->tenant_city,
            'product_name' => $p->product_name,
            'description' => $p->description,
            'min_amount' => (float) $p->min_amount,
            'max_amount' => (float) $p->max_amount,
            'interest_rate' => (float) $p->interest_rate,
            'interest_type' => $p->interest_type,
            'interest_period' => $p->interest_period,
            'min_tenure' => $p->min_tenure,
            'max_tenure' => $p->max_tenure,
            'tenure_type' => $p->tenure_type,
            'repayment_schedule' => $p->repayment_schedule,
            'processing_fee' => (float) $p->processing_fee,
            'requires_collateral' => $p->requires_collateral,
            'requires_guarantor' => $p->requires_guarantor,
            'applications_count' => $p->applications_count,
        ];
    }
}
