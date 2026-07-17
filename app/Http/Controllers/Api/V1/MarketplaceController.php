<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\MarketplaceInterest;
use App\Models\Tenant\MarketplaceListing;
use App\Models\Tenant\MarketplaceReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace — Lender / staff facing.
 * Browse active borrower listings, express interest, post reviews.
 */
class MarketplaceController extends BaseApiController
{
    /**
     * GET /api/v1/marketplace/listings
     * Browse active listings with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $listings = MarketplaceListing::active()
            ->with(['borrower:id,first_name,last_name,credit_score', 'interests'])
            ->when($request->purpose, fn ($q, $v) => $q->where('purpose', $v))
            ->when($request->min_amount, fn ($q, $v) => $q->where('amount_requested', '>=', $v))
            ->when($request->max_amount, fn ($q, $v) => $q->where('amount_requested', '<=', $v))
            ->latest('published_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success([
            'data'       => $listings->map(fn ($l) => $this->formatListing($l)),
            'pagination' => [
                'total'        => $listings->total(),
                'per_page'     => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page'    => $listings->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/marketplace/listings/{id}
     */
    public function show(int $id): JsonResponse
    {
        $listing = MarketplaceListing::active()
            ->with([
                'borrower:id,first_name,last_name,credit_score,occupation,employer',
                'interests.user:id,name',
                'reviews.reviewer:id,name',
            ])
            ->findOrFail($id);

        return $this->success($this->formatListing($listing, true));
    }

    /**
     * POST /api/v1/marketplace/listings/{id}/express-interest
     */
    public function expressInterest(Request $request, int $id): JsonResponse
    {
        $listing = MarketplaceListing::active()->findOrFail($id);

        $data = $request->validate([
            'amount_offered' => ['nullable', 'numeric', 'min:1'],
            'interest_rate'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'message'        => ['nullable', 'string', 'max:500'],
        ]);

        $interest = MarketplaceInterest::updateOrCreate(
            ['listing_id' => $listing->id, 'user_id' => $request->user()->id],
            array_merge($data, ['status' => 'pending'])
        );

        return $this->success($interest, 'Interest expressed.', 201);
    }

    /**
     * GET /api/v1/marketplace/my-interests
     * Return all listings this lender has expressed interest in.
     */
    public function myInterests(Request $request): JsonResponse
    {
        $interests = MarketplaceInterest::where('user_id', $request->user()->id)
            ->with('listing:id,title,amount_requested,status,borrower_id')
            ->latest()
            ->paginate(20);

        return $this->success([
            'data'       => $interests->items(),
            'pagination' => ['total' => $interests->total(), 'current_page' => $interests->currentPage(), 'last_page' => $interests->lastPage()],
        ]);
    }

    /**
     * GET /api/v1/marketplace/reviews/{listingId}
     */
    public function reviews(int $globalId): JsonResponse
    {
        $listing = MarketplaceListing::findOrFail($globalId);
        $reviews = $listing->reviews()->with('reviewer:id,name')->latest()->get();

        return $this->success([
            'listing_id'    => $listing->id,
            'average_rating'=> $reviews->avg('rating'),
            'reviews'       => $reviews->map(fn ($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'reviewer'   => $r->reviewer->name,
                'created_at' => $r->created_at->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * POST /api/v1/marketplace/reviews
     */
    public function postReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:marketplace_listings,id'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['nullable', 'string', 'max:1000'],
        ]);

        $review = MarketplaceReview::updateOrCreate(
            ['listing_id' => $data['listing_id'], 'reviewer_id' => $request->user()->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null]
        );

        return $this->success($review, 'Review posted.', 201);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatListing(MarketplaceListing $listing, bool $full = false): array
    {
        $data = [
            'id'                    => $listing->id,
            'title'                 => $listing->title,
            'description'           => $full ? $listing->description : null,
            'amount_requested'      => (float) $listing->amount_requested,
            'interest_rate_offered' => $listing->interest_rate_offered ? (float) $listing->interest_rate_offered : null,
            'purpose'               => $listing->purpose,
            'tenure_months'         => $listing->tenure_months,
            'status'                => $listing->status,
            'interests_count'       => $listing->interests?->count() ?? 0,
            'published_at'          => $listing->published_at?->toDateTimeString(),
            'expires_at'            => $listing->expires_at?->toDateTimeString(),
            'borrower'              => $listing->borrower ? [
                'id'           => $listing->borrower->id,
                'name'         => $listing->borrower->full_name,
                'credit_score' => $listing->borrower->credit_score,
            ] : null,
        ];

        if ($full) {
            $data['interests'] = $listing->interests?->map(fn ($i) => [
                'id'             => $i->id,
                'user'           => $i->user->name ?? null,
                'amount_offered' => $i->amount_offered ? (float) $i->amount_offered : null,
                'interest_rate'  => $i->interest_rate  ? (float) $i->interest_rate  : null,
                'status'         => $i->status,
                'message'        => $i->message,
            ]);
        }

        return array_filter($data, fn ($v) => $v !== null);
    }
}
