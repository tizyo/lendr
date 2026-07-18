<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoyaltyTier;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends BaseApiController
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    /** GET /api/v1/borrowers/{borrower}/loyalty */
    public function show(Borrower $borrower): JsonResponse
    {
        return $this->success($this->loyalty->ledger($borrower));
    }

    /** POST /api/v1/borrowers/{borrower}/loyalty/redeem */
    public function redeem(Request $request, Borrower $borrower): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        try {
            $account = $this->loyalty->redeem($borrower, $data['points'], $data['description'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'total_points' => $account->total_points,
            'tier' => $account->tier,
        ], 'Points redeemed successfully.');
    }

    /** GET /api/v1/loyalty/tiers */
    public function tiers(): JsonResponse
    {
        $tiers = LoyaltyTier::where('is_active', true)
            ->orderBy('min_points')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'min_points' => $t->min_points,
                'fee_discount_pct' => $t->fee_discount_pct,
            ]);

        return $this->success($tiers);
    }

    /** POST /api/v1/loyalty/tiers — upsert a tier (admin) */
    public function upsertTier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'min_points' => ['required', 'integer', 'min:0'],
            'fee_discount_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $tier = LoyaltyTier::updateOrCreate(
            ['name' => $data['name']],
            [
                'min_points' => $data['min_points'],
                'fee_discount_pct' => $data['fee_discount_pct'],
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return $this->success([
            'id' => $tier->id,
            'name' => $tier->name,
            'min_points' => $tier->min_points,
            'fee_discount_pct' => $tier->fee_discount_pct,
        ], 'Tier saved.', 201);
    }
}
