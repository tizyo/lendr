<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\CollateralItem;
use App\Models\Tenant\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollateralController extends BaseApiController
{
    /**
     * GET /api/v1/loans/{loan}/collateral
     */
    public function index(Loan $loan): JsonResponse
    {
        return $this->success(
            $loan->collateralItems()->get()->map(fn ($c) => $this->format($c)),
        );
    }

    /**
     * POST /api/v1/loans/{loan}/collateral
     */
    public function store(Request $request, Loan $loan): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:property,vehicle,equipment,land,savings,other'],
            'description' => ['required', 'string', 'max:500'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assessed_value' => ['nullable', 'numeric', 'min:0'],
            'assessment_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = $loan->collateralItems()->create($data);

        return $this->success($this->format($item), 'Collateral item added.', 201);
    }

    /**
     * GET /api/v1/collateral/{collateral}
     */
    public function show(CollateralItem $collateral): JsonResponse
    {
        return $this->success($this->format($collateral));
    }

    /**
     * PUT /api/v1/collateral/{collateral}
     */
    public function update(Request $request, CollateralItem $collateral): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', 'required', 'in:property,vehicle,equipment,land,savings,other'],
            'description' => ['sometimes', 'required', 'string', 'max:500'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assessed_value' => ['nullable', 'numeric', 'min:0'],
            'assessment_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:pending,verified,released'],
            'notes' => ['nullable', 'string'],
        ]);

        $collateral->update($data);

        return $this->success($this->format($collateral), 'Collateral item updated.');
    }

    /**
     * DELETE /api/v1/collateral/{collateral}
     */
    public function destroy(CollateralItem $collateral): JsonResponse
    {
        $collateral->delete();

        return $this->success(null, 'Collateral item removed.');
    }

    // ─── Format ───────────────────────────────────────────────────────────────

    private function format(CollateralItem $c): array
    {
        return [
            'id' => $c->id,
            'loan_id' => $c->loan_id,
            'type' => $c->type,
            'type_label' => $c->typeLabel(),
            'description' => $c->description,
            'estimated_value' => $c->estimated_value ? (float) $c->estimated_value : null,
            'assessed_value' => $c->assessed_value ? (float) $c->assessed_value : null,
            'assessment_date' => $c->assessment_date?->toDateString(),
            'location' => $c->location,
            'status' => $c->status,
            'status_badge' => $c->statusBadge(),
            'notes' => $c->notes,
            'created_at' => $c->created_at?->toDateString(),
        ];
    }
}
