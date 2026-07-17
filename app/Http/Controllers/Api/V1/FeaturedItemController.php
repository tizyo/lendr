<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FeaturedRepoItem;
use App\Models\Landlord\RepoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant staff: manage their own featured repo item slots.
 *
 * Flow:
 *   1. GET  /api/v1/featured-items             — list tenant's featured slots
 *   2. POST /api/v1/featured-items              — initiate a paid featuring (returns payment details)
 *   3. POST /api/v1/featured-items/{id}/confirm — confirm payment + activate slot
 *   4. DELETE /api/v1/featured-items/{id}       — cancel / remove a featured slot
 */
class FeaturedItemController extends BaseApiController
{
    /**
     * GET /api/v1/featured-items
     * List all featured slots for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = tenancy()->tenant->id;

        $slots = FeaturedRepoItem::where('tenant_id', $tenantId)
            ->with('repoItem')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated($slots, fn ($s) => $this->formatSlot($s));
    }

    /**
     * POST /api/v1/featured-items
     * Initiate a paid featuring request.
     * Validates max-10 limit and returns payment amount.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = tenancy()->tenant->id;

        $data = $request->validate([
            'repo_item_id' => ['required', 'integer', 'exists:repo_items,id'],
            'days'         => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        // Verify the item belongs to this tenant
        $item = RepoItem::where('id', $data['repo_item_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Check item is not already actively featured
        $alreadyFeatured = FeaturedRepoItem::active()
            ->where('repo_item_id', $item->id)
            ->exists();

        if ($alreadyFeatured) {
            return $this->error('This item is already actively featured.', 422);
        }

        // Max 10 active paid slots per tenant
        $activeCount = FeaturedRepoItem::activePaidCountForTenant($tenantId);
        if ($activeCount >= FeaturedRepoItem::MAX_ACTIVE_PER_TENANT) {
            return $this->error(
                'You have reached the maximum of ' . FeaturedRepoItem::MAX_ACTIVE_PER_TENANT . ' active featured items. '
                . 'Please remove an existing featured item before adding a new one.',
                422
            );
        }

        $days      = (int) $data['days'];
        $amount    = FeaturedRepoItem::costForDays($days);
        $reference = 'FEAT-' . strtoupper(substr(md5($tenantId . $item->id . now()->timestamp), 0, 10));

        // Create a pending slot — activation happens on payment confirmation
        $slot = FeaturedRepoItem::create([
            'repo_item_id'      => $item->id,
            'tenant_id'         => $tenantId,
            'type'              => 'paid',
            'amount_paid'       => $amount,
            'days_paid'         => $days,
            'payment_reference' => $reference,
            'payment_status'    => 'pending',
            'is_active'         => false,
        ]);

        return $this->success([
            'slot'              => $this->formatSlot($slot),
            'payment_reference' => $reference,
            'amount_due'        => $amount,
            'days'              => $days,
            'rate_per_day'      => FeaturedRepoItem::RATE_PER_DAY,
            'instructions'      => 'Pay K' . number_format($amount, 2) . ' via mobile money or bank transfer. '
                                 . 'Use reference: ' . $reference . '. '
                                 . 'Your item will be featured for ' . $days . ' day(s) from payment confirmation.',
        ], 'Featuring initiated. Complete payment to activate.', 201);
    }

    /**
     * POST /api/v1/featured-items/{id}/confirm
     * Confirm payment and activate the featured slot.
     * (In a real flow this would be a webhook; here staff can manually confirm after payment.)
     */
    public function confirmPayment(Request $request, int $id): JsonResponse
    {
        $tenantId = tenancy()->tenant->id;

        $slot = FeaturedRepoItem::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'pending')
            ->firstOrFail();

        $data = $request->validate([
            'payment_proof' => ['nullable', 'string', 'max:255'], // optional receipt ref
        ]);

        $now = now();
        $slot->update([
            'payment_status' => 'confirmed',
            'is_active'      => true,
            'starts_at'      => $now,
            'expires_at'     => $now->copy()->addDays($slot->days_paid),
        ]);

        return $this->success($this->formatSlot($slot->fresh()), 'Payment confirmed. Your item is now featured!');
    }

    /**
     * DELETE /api/v1/featured-items/{id}
     * Deactivate a featured slot (no refund).
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = tenancy()->tenant->id;

        $slot = FeaturedRepoItem::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $slot->update(['is_active' => false]);

        return $this->success(null, 'Featured slot removed.');
    }

    /**
     * GET /api/v1/featured-items/quote?days=7
     */
    public function quote(Request $request): JsonResponse
    {
        $days   = max(1, min(90, (int) $request->get('days', 1)));
        $amount = FeaturedRepoItem::costForDays($days);

        return $this->success([
            'days'         => $days,
            'rate_per_day' => FeaturedRepoItem::RATE_PER_DAY,
            'total'        => $amount,
            'summary'      => "K{$amount} for {$days} day(s) of featured placement",
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatSlot(FeaturedRepoItem $slot): array
    {
        $item = $slot->repoItem;
        return [
            'id'                => $slot->id,
            'repo_item_id'      => $slot->repo_item_id,
            'item_title'        => $item?->title,
            'item_price'        => $item ? (float) $item->price : null,
            'type'              => $slot->type,
            'amount_paid'       => $slot->amount_paid,
            'days_paid'         => $slot->days_paid,
            'payment_reference' => $slot->payment_reference,
            'payment_status'    => $slot->payment_status,
            'is_active'         => $slot->is_active,
            'starts_at'         => $slot->starts_at?->toDateTimeString(),
            'expires_at'        => $slot->expires_at?->toDateTimeString(),
            'days_remaining'    => $slot->daysRemaining(),
            'is_expired'        => $slot->isExpired(),
        ];
    }
}
