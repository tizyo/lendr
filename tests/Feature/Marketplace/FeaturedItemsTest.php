<?php

use App\Models\FeaturedRepoItem;
use App\Models\HotDeal;
use App\Models\HotDealLead;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\RepoItem;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Str;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function featTenant(string $plan = 'growth'): Tenant
{
    return Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Lender '.uniqid(),
        'slug' => 'ldr-'.uniqid(),
        'plan' => $plan,
        'status' => 'active',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
    ]);
}

function featRepoItem(Tenant $tenant, bool $active = true): RepoItem
{
    return RepoItem::create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'title' => 'Test Item '.uniqid(),
        'price' => 5000,
        'category' => 'electronics',
        'condition' => 'good',
        'is_active' => $active,
        'is_sold' => false,
    ]);
}

afterEach(function () {
    HotDealLead::query()->delete();
    HotDeal::query()->delete();
    FeaturedRepoItem::query()->delete();
    RepoItem::query()->delete();
    Tenant::all()->each(fn ($t) => $t->delete());
});

// ═══════════════════════════════════════════════════════════════════════════
// Section 1 — Public featured items endpoint
// ═══════════════════════════════════════════════════════════════════════════

it('public featured-items returns active featured slots only', function () {
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    // Active slot
    FeaturedRepoItem::create([
        'repo_item_id' => $item->id,
        'tenant_id' => $tenant->id,
        'type' => 'paid',
        'payment_status' => 'confirmed',
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDays(5),
    ]);

    // Inactive (expired) slot — should NOT appear
    FeaturedRepoItem::create([
        'repo_item_id' => $item->id,
        'tenant_id' => $tenant->id,
        'type' => 'paid',
        'payment_status' => 'confirmed',
        'is_active' => false,
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson('/api/v1/public/featured-items')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('public featured-items returns empty when no active slots', function () {
    $this->getJson('/api/v1/public/featured-items')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ═══════════════════════════════════════════════════════════════════════════
// Section 2 — FeaturedRepoItem model helpers
// ═══════════════════════════════════════════════════════════════════════════

it('costForDays calculates K50 per day', function () {
    expect(FeaturedRepoItem::costForDays(1))->toBe(50.0);
    expect(FeaturedRepoItem::costForDays(7))->toBe(350.0);
    expect(FeaturedRepoItem::costForDays(30))->toBe(1500.0);
});

it('activePaidCountForTenant counts only active paid slots', function () {
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    // Active paid
    FeaturedRepoItem::create([
        'repo_item_id' => $item->id, 'tenant_id' => $tenant->id,
        'type' => 'paid', 'payment_status' => 'confirmed',
        'is_active' => true, 'starts_at' => now()->subDay(),
        'expires_at' => now()->addDays(3),
    ]);
    // Active manual (should not count toward paid limit)
    FeaturedRepoItem::create([
        'repo_item_id' => $item->id, 'tenant_id' => $tenant->id,
        'type' => 'manual', 'payment_status' => 'confirmed',
        'is_active' => true, 'starts_at' => now()->subDay(),
    ]);

    expect(FeaturedRepoItem::activePaidCountForTenant($tenant->id))->toBe(1);
});

it('daysRemaining returns 0 for already-expired slots', function () {
    $slot = new FeaturedRepoItem(['expires_at' => now()->subDay()]);
    expect($slot->daysRemaining())->toBe(0);
});

it('daysRemaining returns -1 for indefinite manual slots', function () {
    $slot = new FeaturedRepoItem(['expires_at' => null]);
    expect($slot->daysRemaining())->toBe(-1);
});

// ═══════════════════════════════════════════════════════════════════════════
// Section 3 — Landlord manual featuring
// ═══════════════════════════════════════════════════════════════════════════

it('landlord can manually feature a repo item', function () {
    $landlord = LandlordUser::factory()->create();
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    $this->actingAs($landlord, 'sanctum')
        ->postJson('/api/v1/landlord/featured-items', [
            'repo_item_id' => $item->id,
            'note' => 'Great listing!',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'manual')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.payment_status', 'confirmed');

    expect(FeaturedRepoItem::where('repo_item_id', $item->id)->where('type', 'manual')->exists())->toBeTrue();
});

it('landlord cannot feature the same item twice (manual)', function () {
    $landlord = LandlordUser::factory()->create();
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    FeaturedRepoItem::create([
        'repo_item_id' => $item->id, 'tenant_id' => $tenant->id,
        'type' => 'manual', 'payment_status' => 'confirmed',
        'is_active' => true, 'starts_at' => now()->subDay(),
    ]);

    $this->actingAs($landlord, 'sanctum')
        ->postJson('/api/v1/landlord/featured-items', ['repo_item_id' => $item->id])
        ->assertStatus(422);
});

it('landlord can remove a featured slot', function () {
    $landlord = LandlordUser::factory()->create();
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    $slot = FeaturedRepoItem::create([
        'repo_item_id' => $item->id, 'tenant_id' => $tenant->id,
        'type' => 'manual', 'payment_status' => 'confirmed',
        'is_active' => true, 'starts_at' => now()->subDay(),
    ]);

    $this->actingAs($landlord, 'sanctum')
        ->deleteJson("/api/v1/landlord/featured-items/{$slot->id}")
        ->assertOk();

    expect($slot->fresh()->is_active)->toBeFalse();
});

it('landlord can confirm a pending tenant payment', function () {
    $landlord = LandlordUser::factory()->create();
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    $slot = FeaturedRepoItem::create([
        'repo_item_id' => $item->id,
        'tenant_id' => $tenant->id,
        'type' => 'paid',
        'payment_status' => 'pending',
        'amount_paid' => 350.00,
        'days_paid' => 7,
        'payment_reference' => 'FEAT-TESTREF01',
        'is_active' => false,
    ]);

    $this->actingAs($landlord, 'sanctum')
        ->postJson("/api/v1/landlord/featured-items/{$slot->id}/confirm-payment")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'confirmed')
        ->assertJsonPath('data.is_active', true);

    $fresh = $slot->fresh();
    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->payment_status)->toBe('confirmed')
        ->and($fresh->expires_at)->not->toBeNull();
});

it('landlord can view all featured slots', function () {
    $landlord = LandlordUser::factory()->create();
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    FeaturedRepoItem::create([
        'repo_item_id' => $item->id, 'tenant_id' => $tenant->id,
        'type' => 'manual', 'payment_status' => 'confirmed',
        'is_active' => true, 'starts_at' => now(),
    ]);

    $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/featured-items')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'item_title', 'tenant_id', 'type', 'is_active']]]);
});

// ═══════════════════════════════════════════════════════════════════════════
// Section 4 — Hot Deals public endpoints
// ═══════════════════════════════════════════════════════════════════════════

it('public hot-deals returns active deals', function () {
    $tenant = featTenant();

    HotDeal::create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'title' => 'Quick Cash Loan',
        'is_active' => true,
        'starts_at' => now(),
    ]);

    // Inactive deal — should NOT appear
    HotDeal::create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'title' => 'Old Deal',
        'is_active' => false,
        'starts_at' => now()->subMonth(),
    ]);

    $this->getJson('/api/v1/public/hot-deals')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('public hot-deals enquire captures a lead', function () {
    $tenant = featTenant();
    $deal = HotDeal::create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'title' => 'Quick Cash Loan',
        'is_active' => true,
        'starts_at' => now(),
    ]);

    $this->postJson("/api/v1/public/hot-deals/{$deal->id}/enquire", [
        'full_name' => 'John Banda',
        'phone' => '+260971234567',
        'message' => 'I need a loan urgently.',
    ])
        ->assertOk();

    expect(HotDealLead::where('hot_deal_id', $deal->id)->count())->toBe(1);
    expect($deal->fresh()->leads_count)->toBe(1);
});

it('public hot-deals enquire validates required fields', function () {
    $tenant = featTenant();
    $deal = HotDeal::create([
        'tenant_id' => $tenant->id, 'tenant_name' => $tenant->name,
        'title' => 'Test', 'is_active' => true, 'starts_at' => now(),
    ]);

    $this->postJson("/api/v1/public/hot-deals/{$deal->id}/enquire", [])
        ->assertStatus(422);
});

// ═══════════════════════════════════════════════════════════════════════════
// Section 5 — Expiry command
// ═══════════════════════════════════════════════════════════════════════════

it('expire-featured-items command deactivates expired slots', function () {
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    $slot = FeaturedRepoItem::create([
        'repo_item_id' => $item->id,
        'tenant_id' => $tenant->id,
        'type' => 'paid',
        'payment_status' => 'confirmed',
        'is_active' => true,
        'starts_at' => now()->subDays(5),
        'expires_at' => now()->subHour(), // already expired
    ]);

    $this->artisan('lendr:expire-featured-items')
        ->assertSuccessful();

    expect($slot->fresh()->is_active)->toBeFalse();
});

it('expire-featured-items command deactivates expired hot deals', function () {
    $tenant = featTenant();

    $deal = HotDeal::create([
        'tenant_id' => $tenant->id,
        'tenant_name' => $tenant->name,
        'title' => 'Expired Deal',
        'is_active' => true,
        'starts_at' => now()->subDays(3),
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('lendr:expire-featured-items')
        ->assertSuccessful();

    expect($deal->fresh()->is_active)->toBeFalse();
});

it('expire-featured-items dry-run does not modify records', function () {
    $tenant = featTenant();
    $item = featRepoItem($tenant);

    $slot = FeaturedRepoItem::create([
        'repo_item_id' => $item->id,
        'tenant_id' => $tenant->id,
        'type' => 'paid',
        'payment_status' => 'confirmed',
        'is_active' => true,
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('lendr:expire-featured-items --dry-run')
        ->assertSuccessful();

    // Still active after dry run
    expect($slot->fresh()->is_active)->toBeTrue();
});
