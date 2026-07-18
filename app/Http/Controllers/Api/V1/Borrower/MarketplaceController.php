<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\MarketplaceInterest;
use App\Models\Tenant\MarketplaceListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace — Borrower-facing.
 * Borrowers can list their loan needs, review offers, accept/decline interest.
 */
class MarketplaceController extends BaseApiController
{
    /**
     * GET /api/v1/borrower/marketplace/listings
     */
    public function myListings(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $listings = MarketplaceListing::where('borrower_id', $borrower->id)
            ->withCount('interests')
            ->latest()
            ->paginate(20);

        return $this->success([
            'data' => $listings->map(fn ($l) => $this->format($l)),
            'pagination' => ['total' => $listings->total(), 'current_page' => $listings->currentPage(), 'last_page' => $listings->lastPage()],
        ]);
    }

    /**
     * POST /api/v1/borrower/marketplace/listings
     */
    public function createListing(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount_requested' => ['required', 'numeric', 'min:1'],
            'interest_rate_offered' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:100'],
            'tenure_months' => ['nullable', 'integer', 'min:1', 'max:360'],
            'publish' => ['boolean'],
        ]);

        $listing = MarketplaceListing::create([
            'borrower_id' => $borrower->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount_requested' => $data['amount_requested'],
            'interest_rate_offered' => $data['interest_rate_offered'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'tenure_months' => $data['tenure_months'] ?? null,
            'status' => ($data['publish'] ?? false) ? 'active' : 'draft',
            'published_at' => ($data['publish'] ?? false) ? now() : null,
            'expires_at' => ($data['publish'] ?? false) ? now()->addDays(30) : null,
        ]);

        return $this->success($this->format($listing), 'Listing created.', 201);
    }

    /**
     * PUT /api/v1/borrower/marketplace/listings/{id}/withdraw
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $listing = MarketplaceListing::where('borrower_id', $borrower->id)->findOrFail($id);

        if (! in_array($listing->status, ['active', 'draft'])) {
            return $this->error('This listing cannot be withdrawn.', 422);
        }

        $listing->update(['status' => 'withdrawn']);

        return $this->success(null, 'Listing withdrawn.');
    }

    /**
     * POST /api/v1/borrower/marketplace/interests/{id}/accept
     * Borrower accepts a specific lender's interest offer.
     */
    public function acceptInterest(Request $request, int $id): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $interest = MarketplaceInterest::with('listing')
            ->whereHas('listing', fn ($q) => $q->where('borrower_id', $borrower->id))
            ->findOrFail($id);

        if ($interest->status !== 'pending') {
            return $this->error('This interest offer is no longer pending.', 422);
        }

        $interest->update(['status' => 'accepted', 'responded_at' => now()]);

        // Decline all other pending interests on the same listing
        MarketplaceInterest::where('listing_id', $interest->listing_id)
            ->where('id', '!=', $id)
            ->where('status', 'pending')
            ->update(['status' => 'declined', 'responded_at' => now()]);

        $interest->listing->update(['status' => 'funded']);

        return $this->success(null, 'Interest accepted. Listing marked as funded.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function format(MarketplaceListing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'amount_requested' => (float) $listing->amount_requested,
            'interest_rate_offered' => $listing->interest_rate_offered ? (float) $listing->interest_rate_offered : null,
            'purpose' => $listing->purpose,
            'tenure_months' => $listing->tenure_months,
            'status' => $listing->status,
            'interests_count' => $listing->interests_count ?? 0,
            'published_at' => $listing->published_at?->toDateTimeString(),
            'expires_at' => $listing->expires_at?->toDateTimeString(),
        ];
    }
}
