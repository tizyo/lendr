<?php

use App\Models\Tenant\Borrower;
use App\Models\Tenant\MarketplaceInterest;
use App\Models\Tenant\MarketplaceListing;
use App\Models\Tenant\User;

function interestBorrower(): Borrower
{
    return Borrower::factory()->create(['is_active' => true]);
}

function interestListing(Borrower $borrower, array $attrs = []): MarketplaceListing
{
    return MarketplaceListing::create(array_merge([
        'borrower_id' => $borrower->id,
        'title' => 'Working capital',
        'amount_requested' => 5000,
        'interest_rate_offered' => 10,
        'purpose' => 'business',
        'tenure_months' => 6,
        'status' => 'active',
        'published_at' => now(),
        'expires_at' => now()->addDays(30),
    ], $attrs));
}

function interestOffer(MarketplaceListing $listing, array $attrs = []): MarketplaceInterest
{
    $lender = User::factory()->create();

    return MarketplaceInterest::create(array_merge([
        'listing_id' => $listing->id,
        'user_id' => $lender->id,
        'amount_offered' => 5000,
        'interest_rate' => 12,
        'status' => 'pending',
    ], $attrs));
}

test('borrower can decline a pending interest offer', function () {
    $borrower = interestBorrower();
    $listing = interestListing($borrower);
    $interest = interestOffer($listing);

    $this->actingAs($borrower, 'sanctum')
        ->putJson(route('api.v1.borrower.marketplace.interests.decline', $interest->id))
        ->assertOk();

    expect($interest->fresh()->status)->toBe('declined');
    // Declining doesn't withdraw the listing - it stays open for other offers.
    expect($listing->fresh()->status)->toBe('active');
});

test('borrower cannot decline an already-resolved interest offer', function () {
    $borrower = interestBorrower();
    $listing = interestListing($borrower);
    $interest = interestOffer($listing, ['status' => 'accepted', 'responded_at' => now()]);

    $this->actingAs($borrower, 'sanctum')
        ->putJson(route('api.v1.borrower.marketplace.interests.decline', $interest->id))
        ->assertStatus(422);
});

test('borrower cannot decline an interest offer on another borrower\'s listing', function () {
    $owner = interestBorrower();
    $listing = interestListing($owner);
    $interest = interestOffer($listing);

    $intruder = interestBorrower();

    $this->actingAs($intruder, 'sanctum')
        ->putJson(route('api.v1.borrower.marketplace.interests.decline', $interest->id))
        ->assertStatus(404);

    expect($interest->fresh()->status)->toBe('pending');
});

test('borrower can accept a pending interest offer and it funds the listing', function () {
    $borrower = interestBorrower();
    $listing = interestListing($borrower);
    $interest = interestOffer($listing);
    $otherPending = interestOffer($listing);

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.marketplace.interests.accept', $interest->id))
        ->assertOk();

    expect($interest->fresh()->status)->toBe('accepted');
    expect($listing->fresh()->status)->toBe('funded');
    // Any other pending offer on the same listing is auto-declined.
    expect($otherPending->fresh()->status)->toBe('declined');
});
