<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\FeaturedRepoItem;
use App\Models\HotDeal;
use App\Models\HotDealLead;
use App\Models\Landlord\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoints for featured items and hot deals.
 * No authentication required.
 */
class FeaturedController extends BaseApiController
{
    /**
     * GET /api/v1/public/featured-items
     * Returns up to 10 active featured items, ordered: manual first then paid (most recent).
     */
    public function featuredItems(): JsonResponse
    {
        $featured = FeaturedRepoItem::active()
            ->with(['repoItem.images'])
            ->orderByRaw("CASE WHEN type = 'manual' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $data = $featured->map(function ($slot) {
            $item = $slot->repoItem;
            if (! $item || ! $item->is_active || $item->is_sold) {
                return null;
            }

            $tenant = Tenant::find($item->tenant_id);
            $primary = $item->images->firstWhere('is_primary', true) ?? $item->images->first();

            return [
                'id' => $item->id,
                'title' => $item->title,
                'price' => (float) $item->price,
                'category' => $item->category,
                'condition' => $item->condition,
                'location' => $item->location,
                'image_url' => $primary?->image_url,
                'tenant_name' => $item->tenant_name,
                'tenant_badge' => $tenant?->verificationBadge(),
                'featured_type' => $slot->type,
                'days_remaining' => $slot->daysRemaining(),
                'featured_since' => $slot->starts_at?->toDateString(),
            ];
        })->filter()->values();

        return $this->success($data);
    }

    /**
     * GET /api/v1/public/hot-deals
     * Returns active hot deals from all lenders.
     */
    public function hotDeals(Request $request): JsonResponse
    {
        $query = HotDeal::active()
            ->when($request->tenant_id, fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->orderByDesc('created_at');

        $deals = $query->paginate(12);

        return $this->paginated($deals, fn ($d) => $this->formatDeal($d));
    }

    /**
     * GET /api/v1/public/hot-deals/{id}
     */
    public function showDeal(int $id): JsonResponse
    {
        $deal = HotDeal::active()->findOrFail($id);

        // Increment views
        $deal->increment('views_count');

        $tenant = Tenant::find($deal->tenant_id);

        return $this->success([
            ...$this->formatDeal($deal),
            'tenant_badge' => $tenant?->verificationBadge(),
            'requirements' => $deal->requirements,
            'contact_phone' => $deal->contact_phone,
            'contact_email' => $deal->contact_email,
        ]);
    }

    /**
     * POST /api/v1/public/hot-deals/{id}/enquire
     * Capture a lead from a Hot Deal.
     */
    public function enquireDeal(Request $request, int $id): JsonResponse
    {
        $deal = HotDeal::active()->findOrFail($id);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        HotDealLead::create([
            ...$data,
            'hot_deal_id' => $deal->id,
            'ip_address' => $request->ip(),
        ]);

        $deal->increment('leads_count');

        return $this->success(null, 'Your enquiry has been submitted. The lender will contact you shortly.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatDeal(HotDeal $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'loan_product' => $d->loan_product,
            'interest_rate' => $d->interest_rate,
            'min_amount' => $d->min_amount,
            'max_amount' => $d->max_amount,
            'tenure' => $d->tenure,
            'badge_label' => $d->badge_label,
            'image_url' => $d->image_url,
            'tenant_id' => $d->tenant_id,
            'tenant_name' => $d->tenant_name,
            'expires_at' => $d->expires_at?->toDateString(),
            'views_count' => $d->views_count,
            'leads_count' => $d->leads_count,
            'created_at' => $d->created_at->toDateString(),
        ];
    }
}
