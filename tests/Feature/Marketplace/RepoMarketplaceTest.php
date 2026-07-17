<?php

use App\Models\Landlord\GhostUser;
use App\Models\Landlord\RepoCart;
use App\Models\Landlord\RepoEnquiry;
use App\Models\Landlord\RepoItem;
use App\Models\Landlord\RepoItemImage;
use App\Models\Tenant\Borrower;
use Illuminate\Support\Facades\DB;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function repoAdmin(): \App\Models\Tenant\User
{
    return \App\Models\Tenant\User::factory()->create(['role' => 'super_admin']);
}

function repoGhost(array $attrs = []): GhostUser
{
    return GhostUser::create(array_merge([
        'name'  => 'Test Ghost',
        'phone' => '+260971000'.rand(100, 999),
        'email' => 'ghost'.rand(1000, 9999).'@test.com',
    ], $attrs));
}

function repoItem(array $attrs = []): RepoItem
{
    return RepoItem::create(array_merge([
        'tenant_id'   => 'local',
        'tenant_name' => 'Test Tenant',
        'title'       => 'Samsung TV 55"',
        'description' => 'Good condition repossessed TV.',
        'price'       => 1500.00,
        'category'    => 'electronics',
        'condition'   => 'good',
        'location'    => 'Lusaka',
        'is_active'   => true,
        'is_sold'     => false,
    ], $attrs));
}

function ghostToken(GhostUser $ghost): string
{
    return $ghost->createToken('marketplace')->plainTextToken;
}

function seedGrowthPlan(): void
{
    // Ensure plan_configs has a growth plan with repo_marketplace
    $existing = DB::table('plan_configs')->where('plan', 'growth')->first();
    if (! $existing) {
        DB::table('plan_configs')->insert([
            'plan'     => 'growth',
            'features' => json_encode(['repo_marketplace' => true]),
            'limits'   => json_encode([]),
        ]);
    } else {
        $features = json_decode($existing->features ?? '{}', true);
        $features['repo_marketplace'] = true;
        DB::table('plan_configs')->where('plan', 'growth')->update(['features' => json_encode($features)]);
    }

    // subscriptions has FK to tenants — ensure 'local' tenant exists
    if (! DB::table('tenants')->where('id', 'local')->exists()) {
        DB::table('tenants')->insert([
            'id'         => 'local',
            'name'       => 'Local Test Tenant',
            'slug'       => 'local',
            'plan'       => 'growth',
            'status'     => 'active',
            'currency'   => 'ZMW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('subscriptions')->insert([
        'tenant_id'  => 'local',
        'plan'       => 'growth',
        'status'     => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ─── Ghost Auth ───────────────────────────────────────────────────────────────

test('ghost user can register', function () {
    $response = $this->postJson('/api/v1/public/auth/register', [
        'name'  => 'John Buyer',
        'phone' => '+260971111001',
        'email' => 'johnbuyer@test.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.is_phone_verified', false);

    $this->assertDatabaseHas('ghost_users', ['phone' => '+260971111001']);
});

test('ghost register is idempotent', function () {
    GhostUser::create(['name' => 'Existing', 'phone' => '+260971111002', 'email' => null]);

    $response = $this->postJson('/api/v1/public/auth/register', [
        'name'  => 'Existing Again',
        'phone' => '+260971111002',
    ]);

    $response->assertStatus(201);
    expect(GhostUser::where('phone', '+260971111002')->count())->toBe(1);
});

test('ghost user can request OTP and verify', function () {
    $ghost = repoGhost(['phone' => '+260971111003']);

    $response = $this->postJson('/api/v1/public/auth/request-otp', ['phone' => $ghost->phone]);
    $response->assertOk();

    $otp = $response->json('data.otp'); // non-production exposes OTP
    expect($otp)->toBeString()->toHaveLength(6);

    $verify = $this->postJson('/api/v1/public/auth/verify-otp', [
        'phone' => $ghost->phone,
        'otp'   => $otp,
    ]);

    $verify->assertOk()
        ->assertJsonStructure(['data' => ['token', 'ghost_user']]);
});

test('otp verify fails with wrong code', function () {
    $ghost = repoGhost(['phone' => '+260971111004']);
    app(\App\Services\GhostUserService::class)->generateOtp($ghost);

    $this->postJson('/api/v1/public/auth/verify-otp', [
        'phone' => $ghost->phone,
        'otp'   => '000000',
    ])->assertStatus(422);
});

test('ghost user can view and update profile', function () {
    $ghost = repoGhost();
    $token = ghostToken($ghost);

    $this->withToken($token)->getJson('/api/v1/public/auth/profile')
        ->assertOk()
        ->assertJsonPath('data.name', $ghost->name);

    $this->withToken($token)->putJson('/api/v1/public/auth/profile', [
        'city' => 'Ndola',
    ])->assertOk();

    expect($ghost->fresh()->city)->toBe('Ndola');
});

// ─── Public Item Browsing (no auth) ──────────────────────────────────────────

test('anyone can browse active unsold items', function () {
    repoItem(['title' => 'Active TV']);
    repoItem(['title' => 'Inactive Fridge', 'is_active' => false]);
    repoItem(['title' => 'Sold Chair', 'is_sold' => true]);

    $response = $this->getJson('/api/v1/public/items');
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Active TV')
        ->not->toContain('Inactive Fridge')
        ->not->toContain('Sold Chair');
});

test('browse supports keyword search', function () {
    repoItem(['title' => 'Dell Laptop', 'description' => 'Intel i7']);
    repoItem(['title' => 'Wooden Chair']);

    $response = $this->getJson('/api/v1/public/items?q=laptop');
    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Dell Laptop')
        ->not->toContain('Wooden Chair');
});

test('browse filters by category', function () {
    repoItem(['title' => 'Sofa', 'category' => 'furniture']);
    repoItem(['title' => 'Phone', 'category' => 'electronics']);

    $response = $this->getJson('/api/v1/public/items?category=furniture');
    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Sofa')
        ->not->toContain('Phone');
});

test('browse filters by price range', function () {
    repoItem(['title' => 'Cheap', 'price' => 100]);
    repoItem(['title' => 'Expensive', 'price' => 5000]);

    $response = $this->getJson('/api/v1/public/items?max_price=500');
    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Cheap')
        ->not->toContain('Expensive');
});

test('viewing item detail increments views_count', function () {
    $item = repoItem();
    expect((int) $item->fresh()->views_count)->toBe(0);

    $this->getJson("/api/v1/public/items/{$item->id}")->assertOk();

    expect($item->fresh()->views_count)->toBe(1);
});

test('item detail returns images', function () {
    $item = repoItem();
    RepoItemImage::create([
        'item_id'   => $item->id,
        'image_url' => 'https://cdn.example.com/tv.jpg',
        'is_primary'=> true,
        'sort_order'=> 0,
    ]);

    $response = $this->getJson("/api/v1/public/items/{$item->id}");
    $response->assertOk()
        ->assertJsonStructure(['data' => ['images']]);

    $images = $response->json('data.images');
    expect($images)->toHaveCount(1)
        ->and($images[0]['image_url'])->toBe('https://cdn.example.com/tv.jpg');
});

// ─── Ghost-Auth: Enquire ─────────────────────────────────────────────────────

test('ghost user can enquire on an item', function () {
    $item  = repoItem();
    $ghost = repoGhost();
    $token = ghostToken($ghost);

    $response = $this->withToken($token)->postJson("/api/v1/public/items/{$item->id}/enquire", [
        'message' => 'Is this still available?',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('repo_enquiries', [
        'item_id'       => $item->id,
        'ghost_user_id' => $ghost->id,
        'message'       => 'Is this still available?',
    ]);

    expect($item->fresh()->enquiries_count)->toBe(1);
});

test('enquiry requires ghost auth', function () {
    $item = repoItem();

    $this->postJson("/api/v1/public/items/{$item->id}/enquire", ['message' => 'Hello'])
        ->assertStatus(401);
});

test('ghost user can view their enquiries', function () {
    $item  = repoItem();
    $ghost = repoGhost();
    RepoEnquiry::create([
        'item_id'       => $item->id,
        'ghost_user_id' => $ghost->id,
        'message'       => 'My enquiry',
    ]);

    $token = ghostToken($ghost);
    $response = $this->withToken($token)->getJson('/api/v1/public/my-enquiries');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['message'])->toBe('My enquiry');
});

// ─── Ghost-Auth: Cart ────────────────────────────────────────────────────────

test('ghost user can add item to cart', function () {
    $item  = repoItem();
    $ghost = repoGhost();
    $token = ghostToken($ghost);

    $response = $this->withToken($token)->postJson('/api/v1/public/cart', [
        'item_id' => $item->id,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('repo_carts', [
        'ghost_user_id' => $ghost->id,
        'item_id'       => $item->id,
    ]);
});

test('cart add is idempotent', function () {
    $item  = repoItem();
    $ghost = repoGhost();
    $token = ghostToken($ghost);

    $this->withToken($token)->postJson('/api/v1/public/cart', ['item_id' => $item->id])->assertStatus(201);
    $this->withToken($token)->postJson('/api/v1/public/cart', ['item_id' => $item->id])->assertStatus(201);

    expect(RepoCart::where('ghost_user_id', $ghost->id)->count())->toBe(1);
});

test('ghost user can view cart', function () {
    $item  = repoItem();
    $ghost = repoGhost();
    RepoCart::create(['ghost_user_id' => $ghost->id, 'item_id' => $item->id]);
    $token = ghostToken($ghost);

    $response = $this->withToken($token)->getJson('/api/v1/public/cart');
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['item']['title'])->toBe($item->title);
});

test('ghost user can remove item from cart', function () {
    $item  = repoItem();
    $ghost = repoGhost();
    $cart  = RepoCart::create(['ghost_user_id' => $ghost->id, 'item_id' => $item->id]);
    $token = ghostToken($ghost);

    $this->withToken($token)->deleteJson("/api/v1/public/cart/{$cart->id}")
        ->assertOk();

    $this->assertDatabaseMissing('repo_carts', ['id' => $cart->id]);
});

// ─── Tenant Repo Item Management ─────────────────────────────────────────────

test('tenant can list their repo items', function () {
    $admin = repoAdmin();
    repoItem(['title' => 'My TV']);
    repoItem(['title' => 'Other Tenant TV', 'tenant_id' => 'other_tenant']);

    $response = $this->actingAs($admin)->getJson('/api/v1/repo-items');
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('My TV')
        ->not->toContain('Other Tenant TV');
});

test('tenant with growth plan can create a repo item', function () {
    seedGrowthPlan();
    $admin = repoAdmin();

    $response = $this->actingAs($admin)->postJson('/api/v1/repo-items', [
        'title'     => 'Repossessed Fridge',
        'price'     => 800,
        'category'  => 'furniture',
        'condition' => 'fair',
        'images'    => [
            ['url' => 'https://cdn.example.com/fridge.jpg', 'caption' => 'Front view'],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Repossessed Fridge');

    $this->assertDatabaseHas('repo_item_images', [
        'image_url'  => 'https://cdn.example.com/fridge.jpg',
        'is_primary' => true,
    ]);
});

test('tenant without growth plan cannot create repo item', function () {
    // No subscription seeded
    $admin = repoAdmin();

    $this->actingAs($admin)->postJson('/api/v1/repo-items', [
        'title'     => 'Blocked Item',
        'price'     => 100,
        'category'  => 'other',
        'condition' => 'poor',
    ])->assertStatus(403);
});

test('tenant can update their repo item', function () {
    $admin = repoAdmin();
    $item  = repoItem();

    $this->actingAs($admin)->putJson("/api/v1/repo-items/{$item->id}", [
        'price' => 2000,
    ])->assertOk();

    expect((float) $item->fresh()->price)->toBe(2000.0);
});

test('tenant can mark item as sold', function () {
    $admin = repoAdmin();
    $item  = repoItem();

    $this->actingAs($admin)->postJson("/api/v1/repo-items/{$item->id}/mark-sold")
        ->assertOk();

    expect($item->fresh()->is_sold)->toBeTrue();
});

test('tenant can deactivate (destroy) a listing', function () {
    $admin = repoAdmin();
    $item  = repoItem();

    $this->actingAs($admin)->deleteJson("/api/v1/repo-items/{$item->id}")
        ->assertOk();

    expect($item->fresh()->is_active)->toBeFalse();
});

test('tenant can view enquiries on their item with ghost user contact info', function () {
    $admin = repoAdmin();
    $item  = repoItem();
    $ghost = repoGhost(['name' => 'Enquiring Ghost', 'phone' => '+260971555000']);
    RepoEnquiry::create([
        'item_id'       => $item->id,
        'ghost_user_id' => $ghost->id,
        'message'       => 'Still available?',
    ]);

    $response = $this->actingAs($admin)->getJson("/api/v1/repo-items/{$item->id}/enquiries");
    $response->assertOk();

    $enquiries = $response->json('data');
    expect($enquiries)->toHaveCount(1)
        ->and($enquiries[0]['enquirer']['name'])->toBe('Enquiring Ghost')
        ->and($enquiries[0]['message'])->toBe('Still available?');
});

test('tenant can reply to an enquiry', function () {
    $admin   = repoAdmin();
    $item    = repoItem();
    $ghost   = repoGhost();
    $enquiry = RepoEnquiry::create([
        'item_id'       => $item->id,
        'ghost_user_id' => $ghost->id,
        'message'       => 'Is it available?',
    ]);

    $this->actingAs($admin)->postJson("/api/v1/repo-items/{$item->id}/enquiries/{$enquiry->id}/reply", [
        'reply' => 'Yes, still available!',
    ])->assertOk();

    expect($enquiry->fresh()->reply)->toBe('Yes, still available!')
        ->and($enquiry->fresh()->status)->toBe('replied');
});

test('tenant cannot access another tenant item', function () {
    $admin = repoAdmin();
    $item  = repoItem(['tenant_id' => 'other_tenant']);

    $this->actingAs($admin)->getJson("/api/v1/repo-items/{$item->id}")
        ->assertStatus(404);
});

// ─── KYC Pull ────────────────────────────────────────────────────────────────

test('staff can lookup ghost user by national id', function () {
    $admin = repoAdmin();
    $ghost = repoGhost();
    $svc   = app(\App\Services\GhostUserService::class);
    $ghost->update([
        'national_id'      => 'NRC123456/78/1',
        'national_id_hash' => $svc->hash('NRC123456/78/1', 'nrc'),
    ]);

    $response = $this->actingAs($admin)->postJson('/api/v1/borrowers/kyc-lookup', [
        'national_id' => 'NRC123456/78/1',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.found', true)
        ->assertJsonPath('data.ghost_user_id', $ghost->id);

    $fields = $response->json('data.fields');
    expect($fields)->toHaveKey('name');
});

test('kyc lookup returns not found when no match', function () {
    $admin = repoAdmin();

    $response = $this->actingAs($admin)->postJson('/api/v1/borrowers/kyc-lookup', [
        'national_id' => 'DOES-NOT-EXIST',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.found', false);
});

test('kyc lookup requires at least one identifier', function () {
    $admin = repoAdmin();

    $this->actingAs($admin)->postJson('/api/v1/borrowers/kyc-lookup', [])
        ->assertStatus(422);
});

test('staff can import selected fields from ghost user into borrower', function () {
    $admin    = repoAdmin();
    $ghost    = repoGhost(['name' => 'Jane Ghost', 'email' => 'jane@ghost.test']);
    $borrower = Borrower::factory()->create(['email' => null]);

    $this->actingAs($admin)->postJson("/api/v1/borrowers/{$borrower->id}/kyc-import", [
        'ghost_user_id' => $ghost->id,
        'fields'        => ['email'],
    ])->assertOk();

    expect($borrower->fresh()->email)->toBe('jane@ghost.test')
        ->and($borrower->fresh()->ghost_user_id)->toBe($ghost->id);
});
