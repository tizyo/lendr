<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\PublicLoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Borrower-facing public loan product marketplace.
 * Allows borrowers to search and express interest in cross-tenant products.
 */
class PublicProductController extends BaseApiController
{
    /**
     * GET /me/public-products
     * Browse active products across all opted-in tenants.
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
            ->orderByDesc('applications_count')
            ->orderBy('interest_rate');

        return $this->paginated($query->paginate(20), fn ($p) => $this->formatProduct($p));
    }

    /**
     * GET /me/public-products/{id}
     * Single product detail.
     */
    public function show(int $id): JsonResponse
    {
        $product = PublicLoanProduct::active()->findOrFail($id);

        return $this->success($this->formatProduct($product));
    }

    /**
     * POST /me/public-products/{id}/apply
     * Borrower expresses interest — increments applications_count.
     */
    public function apply(int $id): JsonResponse
    {
        $product = PublicLoanProduct::active()->findOrFail($id);
        $product->increment('applications_count');

        return $this->success(
            ['product' => $this->formatProduct($product->fresh())],
            'Interest registered. Contact the lender directly to proceed.',
        );
    }

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
