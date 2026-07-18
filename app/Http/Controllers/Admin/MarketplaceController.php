<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PublicLoanProduct;
use App\Models\Landlord\RepoItem;
use App\Models\Tenant\MarketplaceListing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $listings = MarketplaceListing::with([
            'borrower:id,first_name,last_name,borrower_number',
            'loan:id,loan_number',
        ])
            ->withCount(['interests', 'reviews'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->purpose, fn ($q, $p) => $q->where('purpose', $p))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhereHas('borrower', fn ($bq) => $bq->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('borrower_number', 'like', "%{$s}%"),
                    );
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $listings->getCollection()->transform(fn ($l) => [
            'id' => $l->id,
            'title' => $l->title,
            'purpose' => $l->purpose,
            'status' => $l->status,
            'amount_requested' => (float) $l->amount_requested,
            'interest_rate_offered' => (float) $l->interest_rate_offered,
            'tenure_months' => $l->tenure_months,
            'interests_count' => $l->interests_count,
            'reviews_count' => $l->reviews_count,
            'published_at' => $l->published_at?->toDateString(),
            'expires_at' => $l->expires_at?->toDateString(),
            'created_at' => $l->created_at->format('d M Y'),
            'borrower' => $l->borrower ? [
                'id' => $l->borrower->id,
                'name' => $l->borrower->full_name,
                'borrower_number' => $l->borrower->borrower_number,
            ] : null,
            'loan_number' => $l->loan?->loan_number,
        ]);

        return Inertia::render('marketplace/Index', [
            'listings' => $listings,
            'filters' => $request->only(['status', 'purpose', 'search']),
        ]);
    }

    public function interests(int $id): Response
    {
        $listing = MarketplaceListing::with([
            'borrower:id,first_name,last_name,borrower_number',
            'loan:id,loan_number',
            'interests.user:id,name',
        ])->findOrFail($id);

        return Inertia::render('marketplace/Interests', [
            'listing' => [
                'id' => $listing->id,
                'title' => $listing->title,
                'status' => $listing->status,
                'amount_requested' => (float) $listing->amount_requested,
                'borrower' => $listing->borrower ? [
                    'name' => $listing->borrower->full_name,
                    'borrower_number' => $listing->borrower->borrower_number,
                ] : null,
                'loan_number' => $listing->loan?->loan_number,
                'interests' => $listing->interests->map(fn ($i) => [
                    'id' => $i->id,
                    'user' => $i->user?->name,
                    'amount_offered' => (float) $i->amount_offered,
                    'interest_rate' => (float) $i->interest_rate,
                    'status' => $i->status,
                    'message' => $i->message,
                    'responded_at' => $i->responded_at?->format('d M Y H:i'),
                    'created_at' => $i->created_at->format('d M Y H:i'),
                ])->values(),
            ],
        ]);
    }

    public function publicProducts(Request $request): Response
    {
        $products = PublicLoanProduct::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('product_name', 'like', "%{$s}%")
                ->orWhere('tenant_name', 'like', "%{$s}%"),
            ))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('applications_count')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $products->getCollection()->transform(fn ($p) => [
            'id' => $p->id,
            'tenant_name' => $p->tenant_name,
            'tenant_city' => $p->tenant_city,
            'product_name' => $p->product_name,
            'min_amount' => (float) $p->min_amount,
            'max_amount' => (float) $p->max_amount,
            'interest_rate' => (float) $p->interest_rate,
            'interest_type' => $p->interest_type,
            'repayment_schedule' => $p->repayment_schedule,
            'is_active' => (bool) $p->is_active,
            'applications_count' => (int) $p->applications_count,
            'created_at' => $p->created_at->format('d M Y'),
        ]);

        return Inertia::render('marketplace/PublicProducts', [
            'products' => $products,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function unpublishProduct(int $id): \Illuminate\Http\RedirectResponse
    {
        $product = PublicLoanProduct::findOrFail($id);
        $product->update(['is_active' => false]);

        return back()->with('success', 'Product removed from marketplace.');
    }

    public function expire(int $id): \Illuminate\Http\RedirectResponse
    {
        $listing = MarketplaceListing::findOrFail($id);

        if (! in_array($listing->status, ['active', 'draft'])) {
            return back()->with('error', 'Only active or draft listings can be expired.');
        }

        $listing->update(['status' => 'expired']);

        return back()->with('success', 'Listing marked as expired.');
    }

    // ─── Repo Marketplace (repossessed items) ──────────────────────────────

    public function repoItems(Request $request): Response
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        $items = RepoItem::where('tenant_id', $tenantId)
            ->with('primaryImage')
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true)->where('is_sold', false))
            ->when($request->status === 'sold', fn ($q) => $q->where('is_sold', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $items->getCollection()->transform(fn ($i) => [
            'id' => $i->id,
            'title' => $i->title,
            'price' => (float) $i->price,
            'original_value' => $i->original_value ? (float) $i->original_value : null,
            'category' => $i->category,
            'condition' => $i->condition,
            'location' => $i->location,
            'is_active' => (bool) $i->is_active,
            'is_sold' => (bool) $i->is_sold,
            'views_count' => (int) $i->views_count,
            'enquiries_count' => (int) $i->enquiries_count,
            'primary_image' => $i->primaryImage?->image_url,
            'created_at' => $i->created_at->format('d M Y'),
        ]);

        return Inertia::render('marketplace/RepoItems', [
            'items' => $items,
            'filters' => $request->only(['status', 'category', 'search']),
        ]);
    }
}
