<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\GhostUser;
use App\Models\Landlord\RepoEnquiry;
use App\Models\Landlord\RepoItem;
use App\Models\Landlord\Tenant;
use App\Services\SMS\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public-facing repossessed items marketplace.
 *
 * Browse/show: no authentication required.
 * Enquire/cart: ghost user authentication required.
 */
class RepoItemController extends BaseApiController
{
    /**
     * GET /api/v1/public/items
     * Browse all active, unsold items. No auth required.
     */
    public function browse(Request $request): JsonResponse
    {
        $query = RepoItem::active()
            ->with('primaryImage')
            ->when($request->q, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$request->q}%")
                ->orWhere('description', 'like', "%{$request->q}%")
                ->orWhere('tenant_name', 'like', "%{$request->q}%"),
            ))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->condition, fn ($q) => $q->where('condition', $request->condition))
            ->when($request->location, fn ($q) => $q->where('location', 'like', "%{$request->location}%"))
            ->when($request->max_price, fn ($q) => $q->where('price', '<=', $request->max_price))
            ->when($request->min_price, fn ($q) => $q->where('price', '>=', $request->min_price))
            ->orderByDesc('created_at');

        $items = $query->paginate(20);

        return $this->paginated($items, fn ($i) => $this->formatItem($i));
    }

    /**
     * GET /api/v1/public/items/{id}
     * Item detail — increments view count. No auth required.
     */
    public function show(int $id): JsonResponse
    {
        $item = RepoItem::active()->with('images')->findOrFail($id);
        $item->increment('views_count');

        return $this->success($this->formatItem($item, full: true));
    }

    /**
     * POST /api/v1/public/items/{id}/enquire  [ghost auth]
     * Submit an enquiry on an item.
     */
    public function enquire(Request $request, int $id): JsonResponse
    {
        /** @var GhostUser $ghostUser */
        $ghostUser = $request->user();

        if (! $ghostUser instanceof GhostUser) {
            return $this->error('Ghost user authentication required.', 403);
        }

        $item = RepoItem::active()->findOrFail($id);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $enquiry = RepoEnquiry::create([
            'item_id' => $item->id,
            'ghost_user_id' => $ghostUser->id,
            'message' => $data['message'],
        ]);

        $item->increment('enquiries_count');

        // Notify tenant staff via SMS (best-effort)
        try {
            $this->notifyTenantOfEnquiry($item, $ghostUser, $data['message']);
        } catch (\Throwable) {
        }

        return $this->success([
            'enquiry_id' => $enquiry->id,
            'status' => 'new',
        ], 'Enquiry submitted. The seller will respond shortly.', 201);
    }

    /**
     * GET /api/v1/public/my-enquiries  [ghost auth]
     * List enquiries made by the current ghost user.
     */
    public function myEnquiries(Request $request): JsonResponse
    {
        /** @var GhostUser $ghostUser */
        $ghostUser = $request->user();

        if (! $ghostUser instanceof GhostUser) {
            return $this->error('Ghost user authentication required.', 403);
        }

        $enquiries = RepoEnquiry::where('ghost_user_id', $ghostUser->id)
            ->with('item.primaryImage')
            ->latest()
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'message' => $e->message,
                'status' => $e->status,
                'reply' => $e->reply,
                'replied_at' => $e->replied_at?->toIso8601String(),
                'created_at' => $e->created_at->toIso8601String(),
                'item' => $e->item ? [
                    'id' => $e->item->id,
                    'title' => $e->item->title,
                    'price' => (float) $e->item->price,
                    'image_url' => $e->item->primaryImage?->image_url,
                ] : null,
            ]);

        return $this->success($enquiries);
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function formatItem(RepoItem $item, bool $full = false): array
    {
        // Resolve tenant verification badge (cached via model)
        $tenant = $item->tenant_id ? Tenant::find($item->tenant_id) : null;

        $data = [
            'id' => $item->id,
            'tenant_name' => $item->tenant_name,
            'tenant_badge' => $tenant?->verificationBadge(),
            'title' => $item->title,
            'price' => (float) $item->price,
            'original_value' => $item->original_value ? (float) $item->original_value : null,
            'category' => $item->category,
            'condition' => $item->condition,
            'location' => $item->location,
            'is_sold' => $item->is_sold,
            'views_count' => $item->views_count,
            'enquiries_count' => $item->enquiries_count,
            'primary_image' => $item->relationLoaded('primaryImage')
                ? $item->primaryImage?->image_url
                : ($item->relationLoaded('images') ? $item->images->firstWhere('is_primary', true)?->image_url : null),
            'created_at' => $item->created_at->toDateString(),
        ];

        if ($full) {
            $data['description'] = $item->description;
            $data['images'] = $item->relationLoaded('images')
                ? $item->images->map(fn ($img) => [
                    'id' => $img->id,
                    'image_url' => $img->image_url,
                    'caption' => $img->caption,
                    'is_primary' => $img->is_primary,
                ])->values()
                : [];
        }

        return $data;
    }

    private function notifyTenantOfEnquiry(RepoItem $item, GhostUser $ghostUser, string $message): void
    {
        // Find a staff phone number to notify (admin or branch manager)
        $staffPhone = DB::table('users')
            ->where('role', 'super_admin')
            ->whereNotNull('phone')
            ->value('phone');

        if (! $staffPhone) {
            return;
        }

        $sms = app(SmsService::class);
        $text = "New enquiry on your listing \"{$item->title}\" from {$ghostUser->name} ({$ghostUser->phone}): \"{$message}\"";
        $sms->send($staffPhone, $text);
    }
}
