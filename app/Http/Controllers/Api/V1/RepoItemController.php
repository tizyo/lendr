<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Landlord\RepoEnquiry;
use App\Models\Landlord\RepoItem;
use App\Models\Landlord\RepoItemImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-side management of repossessed items marketplace listings.
 * Requires staff authentication AND Growth/Enterprise plan.
 */
class RepoItemController extends BaseApiController
{
    /**
     * GET /api/v1/repo-items
     * List this tenant's repo item listings.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        $items = RepoItem::where('tenant_id', $tenantId)
            ->with('primaryImage')
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true)->where('is_sold', false))
            ->when($request->status === 'sold', fn ($q) => $q->where('is_sold', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($items, fn ($i) => $this->formatItem($i));
    }

    /**
     * POST /api/v1/repo-items
     * Publish a new repo item listing. Growth/Enterprise only.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->hasRepoMarketplaceFeature()) {
            return $this->error('Repo marketplace requires Growth or Enterprise plan.', 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'original_value' => ['nullable', 'numeric', 'min:0'],
            'category' => ['required', 'in:furniture,electronics,vehicle,land,equipment,other'],
            'condition' => ['required', 'in:new,good,fair,poor'],
            'location' => ['nullable', 'string', 'max:100'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*.url' => ['required_with:images', 'url', 'max:1024'],
            'images.*.caption' => ['nullable', 'string', 'max:200'],
        ]);

        $tenantId = (string) (tenant('id') ?? 'local');
        $tenantName = $this->resolveTenantName();

        $item = RepoItem::create([
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantName,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'original_value' => $data['original_value'] ?? null,
            'category' => $data['category'],
            'condition' => $data['condition'],
            'location' => $data['location'] ?? null,
        ]);

        // Attach images
        foreach (($data['images'] ?? []) as $idx => $img) {
            RepoItemImage::create([
                'item_id' => $item->id,
                'image_url' => $img['url'],
                'caption' => $img['caption'] ?? null,
                'is_primary' => $idx === 0,
                'sort_order' => $idx,
            ]);
        }

        return $this->success($this->formatItem($item->load('primaryImage')), 'Item listed on marketplace.', 201);
    }

    /**
     * GET /api/v1/repo-items/{id}
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');
        $item = RepoItem::where('tenant_id', $tenantId)->with('images')->findOrFail($id);

        return $this->success($this->formatItem($item, full: true));
    }

    /**
     * PUT /api/v1/repo-items/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');
        $item = RepoItem::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'original_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'category' => ['sometimes', 'in:furniture,electronics,vehicle,land,equipment,other'],
            'condition' => ['sometimes', 'in:new,good,fair,poor'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item->update($data);

        return $this->success($this->formatItem($item->fresh()), 'Item updated.');
    }

    /**
     * DELETE /api/v1/repo-items/{id}
     * Soft-removes (deactivates) the listing.
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');
        $item = RepoItem::where('tenant_id', $tenantId)->findOrFail($id);
        $item->update(['is_active' => false]);

        return $this->success(null, 'Listing removed from marketplace.');
    }

    /**
     * POST /api/v1/repo-items/{id}/mark-sold
     */
    public function markSold(int $id): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');
        $item = RepoItem::where('tenant_id', $tenantId)->findOrFail($id);
        $item->update(['is_sold' => true]);

        return $this->success(null, 'Item marked as sold.');
    }

    /**
     * GET /api/v1/repo-items/{id}/enquiries
     * View all enquiries received for this item.
     */
    public function enquiries(int $id): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');
        $item = RepoItem::where('tenant_id', $tenantId)->findOrFail($id);

        $enquiries = RepoEnquiry::where('item_id', $item->id)
            ->with('ghostUser:id,name,phone,email')
            ->latest()
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'message' => $e->message,
                'status' => $e->status,
                'reply' => $e->reply,
                'replied_at' => $e->replied_at?->toIso8601String(),
                'created_at' => $e->created_at->toIso8601String(),
                'enquirer' => $e->ghostUser ? [
                    'id' => $e->ghostUser->id,
                    'name' => $e->ghostUser->name,
                    'phone' => $e->ghostUser->phone,
                    'email' => $e->ghostUser->email,
                ] : null,
            ]);

        return $this->success($enquiries);
    }

    /**
     * POST /api/v1/repo-items/{id}/enquiries/{enquiryId}/reply
     * Reply to an enquiry.
     */
    public function reply(Request $request, int $id, int $enquiryId): JsonResponse
    {
        $tenantId = (string) (tenant('id') ?? 'local');
        $item = RepoItem::where('tenant_id', $tenantId)->findOrFail($id);

        $enquiry = RepoEnquiry::where('item_id', $item->id)->findOrFail($enquiryId);

        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);

        $enquiry->update([
            'reply' => $data['reply'],
            'status' => 'replied',
            'replied_at' => now(),
        ]);

        return $this->success(null, 'Reply sent.');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function hasRepoMarketplaceFeature(): bool
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        $sub = DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $sub) {
            return false;
        }

        $plan = DB::table('plan_configs')->where('plan', $sub->plan)->first();
        if (! $plan) {
            return false;
        }

        $features = json_decode($plan->features ?? '{}', true);

        return (bool) ($features['repo_marketplace'] ?? false);
    }

    private function resolveTenantName(): string
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        // Try to pull from tenants table if available
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        return $tenant?->name ?? $tenantId;
    }

    private function formatItem(RepoItem $item, bool $full = false): array
    {
        $data = [
            'id' => $item->id,
            'title' => $item->title,
            'price' => (float) $item->price,
            'original_value' => $item->original_value ? (float) $item->original_value : null,
            'category' => $item->category,
            'condition' => $item->condition,
            'location' => $item->location,
            'is_active' => $item->is_active,
            'is_sold' => $item->is_sold,
            'views_count' => $item->views_count,
            'enquiries_count' => $item->enquiries_count,
            'primary_image' => $item->relationLoaded('primaryImage')
                ? $item->primaryImage?->image_url
                : null,
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
}
