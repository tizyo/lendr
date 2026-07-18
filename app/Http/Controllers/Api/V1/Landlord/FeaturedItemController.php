<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\FeaturedRepoItem;
use App\Models\HotDeal;
use App\Models\Landlord\RepoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Landlord superadmin: manage all featured repo items and hot deals.
 */
class FeaturedItemController extends BaseApiController
{
    // ─── Featured Items ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/landlord/featured-items
     * All featured slots across all tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $slots = FeaturedRepoItem::with('repoItem')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->tenant_id, fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->when($request->active_only, fn ($q) => $q->active())
            ->orderByDesc('created_at')
            ->paginate(25);

        return $this->paginated($slots, fn ($s) => $this->formatSlot($s));
    }

    /**
     * POST /api/v1/landlord/featured-items
     * Manually feature a repo item (free, indefinite by default).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'repo_item_id' => ['required', 'integer', 'exists:repo_items,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $item = RepoItem::findOrFail($data['repo_item_id']);

        // Check not already manually featured
        $existing = FeaturedRepoItem::active()
            ->where('repo_item_id', $item->id)
            ->where('type', 'manual')
            ->exists();

        if ($existing) {
            return $this->error('This item already has an active manual featured slot.', 422);
        }

        $slot = FeaturedRepoItem::create([
            'repo_item_id' => $item->id,
            'tenant_id' => $item->tenant_id,
            'type' => 'manual',
            'payment_status' => 'confirmed',
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
            'approved_by' => $request->user()?->id,
            'admin_note' => $data['note'] ?? null,
        ]);

        return $this->success($this->formatSlot($slot->fresh()), 'Item manually featured.', 201);
    }

    /**
     * DELETE /api/v1/landlord/featured-items/{id}
     * Remove any featured slot.
     */
    public function destroy(int $id): JsonResponse
    {
        $slot = FeaturedRepoItem::findOrFail($id);
        $slot->update(['is_active' => false]);

        return $this->success(null, 'Featured slot removed.');
    }

    /**
     * POST /api/v1/landlord/featured-items/{id}/confirm-payment
     * Admin manually confirms a tenant's pending payment.
     */
    public function confirmPayment(Request $request, int $id): JsonResponse
    {
        $slot = FeaturedRepoItem::where('payment_status', 'pending')->findOrFail($id);

        $now = now();
        $slot->update([
            'payment_status' => 'confirmed',
            'is_active' => true,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addDays($slot->days_paid),
            'approved_by' => $request->user()?->id,
            'admin_note' => $request->input('note'),
        ]);

        return $this->success($this->formatSlot($slot->fresh()), 'Payment confirmed and slot activated.');
    }

    // ─── Hot Deals ────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/landlord/hot-deals
     */
    public function hotDeals(Request $request): JsonResponse
    {
        $deals = HotDeal::when($request->tenant_id, fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->when($request->active_only, fn ($q) => $q->active())
            ->orderByDesc('created_at')
            ->paginate(25);

        return $this->paginated($deals, fn ($d) => [
            'id' => $d->id,
            'title' => $d->title,
            'tenant_id' => $d->tenant_id,
            'tenant_name' => $d->tenant_name,
            'is_active' => $d->is_active,
            'badge_label' => $d->badge_label,
            'views_count' => $d->views_count,
            'leads_count' => $d->leads_count,
            'expires_at' => $d->expires_at?->toDateString(),
            'created_at' => $d->created_at->toDateString(),
        ]);
    }

    /**
     * DELETE /api/v1/landlord/hot-deals/{id}
     * Landlord can remove any hot deal (e.g. policy violation).
     */
    public function destroyDeal(int $id): JsonResponse
    {
        HotDeal::findOrFail($id)->delete();

        return $this->success(null, 'Hot Deal removed.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatSlot(FeaturedRepoItem $slot): array
    {
        $item = $slot->repoItem;

        return [
            'id' => $slot->id,
            'repo_item_id' => $slot->repo_item_id,
            'item_title' => $item?->title,
            'item_price' => $item ? (float) $item->price : null,
            'tenant_id' => $slot->tenant_id,
            'type' => $slot->type,
            'amount_paid' => $slot->amount_paid,
            'days_paid' => $slot->days_paid,
            'payment_reference' => $slot->payment_reference,
            'payment_status' => $slot->payment_status,
            'is_active' => $slot->is_active,
            'starts_at' => $slot->starts_at?->toDateTimeString(),
            'expires_at' => $slot->expires_at?->toDateTimeString(),
            'days_remaining' => $slot->daysRemaining(),
            'admin_note' => $slot->admin_note,
        ];
    }
}
