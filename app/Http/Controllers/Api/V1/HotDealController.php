<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\HotDeal;
use App\Models\HotDealLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant staff: manage their Hot Deals promotions.
 */
class HotDealController extends BaseApiController
{
    /**
     * GET /api/v1/hot-deals
     */
    public function index(): JsonResponse
    {
        $tenantId = tenancy()->tenant->id;

        $deals = HotDeal::where('tenant_id', $tenantId)
            ->withCount('leads')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated($deals, fn ($d) => $this->format($d));
    }

    /**
     * POST /api/v1/hot-deals
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'loan_product'  => ['nullable', 'string', 'max:100'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_amount'    => ['nullable', 'numeric', 'min:0'],
            'tenure'        => ['nullable', 'string', 'max:50'],
            'requirements'  => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'badge_label'   => ['nullable', 'string', 'max:30'],
            'image_url'     => ['nullable', 'url', 'max:1024'],
            'expires_at'    => ['nullable', 'date', 'after:today'],
        ]);

        $tenant = tenancy()->tenant;

        $deal = HotDeal::create([
            ...$data,
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'is_active'   => true,
            'starts_at'   => now(),
        ]);

        return $this->success($this->format($deal), 'Hot Deal created successfully.', 201);
    }

    /**
     * PUT /api/v1/hot-deals/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $deal = $this->ownedDeal($id);

        $data = $request->validate([
            'title'         => ['sometimes', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'loan_product'  => ['nullable', 'string', 'max:100'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_amount'    => ['nullable', 'numeric', 'min:0'],
            'tenure'        => ['nullable', 'string', 'max:50'],
            'requirements'  => ['nullable', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'badge_label'   => ['nullable', 'string', 'max:30'],
            'image_url'     => ['nullable', 'url', 'max:1024'],
            'expires_at'    => ['nullable', 'date'],
            'is_active'     => ['sometimes', 'boolean'],
        ]);

        $deal->update($data);

        return $this->success($this->format($deal->fresh()), 'Hot Deal updated.');
    }

    /**
     * DELETE /api/v1/hot-deals/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $deal = $this->ownedDeal($id);
        $deal->delete();

        return $this->success(null, 'Hot Deal removed.');
    }

    /**
     * GET /api/v1/hot-deals/{id}/leads
     */
    public function leads(int $id): JsonResponse
    {
        $deal  = $this->ownedDeal($id);
        $leads = $deal->leads()->orderByDesc('created_at')->paginate(20);

        return $this->paginated($leads, fn ($l) => [
            'id'         => $l->id,
            'full_name'  => $l->full_name,
            'phone'      => $l->phone,
            'email'      => $l->email,
            'message'    => $l->message,
            'created_at' => $l->created_at->toDateTimeString(),
        ]);
    }

    /**
     * POST /api/v1/hot-deals/{id}/toggle
     * Quick toggle active/inactive.
     */
    public function toggle(int $id): JsonResponse
    {
        $deal = $this->ownedDeal($id);
        $deal->update(['is_active' => ! $deal->is_active]);

        return $this->success($this->format($deal->fresh()), 'Status updated.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function ownedDeal(int $id): HotDeal
    {
        return HotDeal::where('tenant_id', tenancy()->tenant->id)
            ->findOrFail($id);
    }

    private function format(HotDeal $d): array
    {
        return [
            'id'            => $d->id,
            'title'         => $d->title,
            'description'   => $d->description,
            'loan_product'  => $d->loan_product,
            'interest_rate' => $d->interest_rate,
            'min_amount'    => $d->min_amount,
            'max_amount'    => $d->max_amount,
            'tenure'        => $d->tenure,
            'requirements'  => $d->requirements,
            'contact_phone' => $d->contact_phone,
            'contact_email' => $d->contact_email,
            'badge_label'   => $d->badge_label,
            'image_url'     => $d->image_url,
            'is_active'     => $d->is_active,
            'starts_at'     => $d->starts_at?->toDateString(),
            'expires_at'    => $d->expires_at?->toDateString(),
            'views_count'   => (int) ($d->views_count ?? 0),
            'leads_count'   => (int) ($d->leads_count ?? 0),
            'created_at'    => $d->created_at->toDateString(),
        ];
    }
}
