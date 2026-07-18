<?php

use App\Models\Tenant\Borrower;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

// ─── OTP flow ────────────────────────────────────────────────────────────────

test('borrower can request an otp for a registered phone', function () {
    Borrower::factory()->create([
        'phone' => '+260971000001',
        'is_active' => true,
    ]);

    $this->postJson(route('api.v1.borrower.auth.request-otp'), [
        'phone' => '+260971000001',
    ])->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('otp request for unknown phone returns 422', function () {
    $this->postJson(route('api.v1.borrower.auth.request-otp'), [
        'phone' => '+260999999999',
    ])->assertStatus(422);
});

test('borrower can verify a valid otp and receive a token', function () {
    $borrower = Borrower::factory()->create([
        'phone' => '+260971000002',
        'is_active' => true,
    ]);

    // Seed the OTP into cache the same way the controller does
    $cacheKey = 'borrower_otp_'.ltrim('+260971000002', '+');
    Cache::put($cacheKey, '123456', now()->addMinutes(5));

    $this->postJson(route('api.v1.borrower.auth.verify-otp'), [
        'phone' => '+260971000002',
        'otp' => '123456',
    ])->assertStatus(200)
        ->assertJsonStructure(['data' => ['token']]);
});

test('borrower otp verify fails with wrong code', function () {
    Borrower::factory()->create([
        'phone' => '+260971000003',
        'is_active' => true,
    ]);

    $cacheKey = 'borrower_otp_'.ltrim('+260971000003', '+');
    Cache::put($cacheKey, '654321', now()->addMinutes(5));

    $this->postJson(route('api.v1.borrower.auth.verify-otp'), [
        'phone' => '+260971000003',
        'otp' => '000000',
    ])->assertStatus(401);
});

test('borrower otp verify fails after expiry', function () {
    Borrower::factory()->create([
        'phone' => '+260971000004',
        'is_active' => true,
    ]);

    // No cache entry = expired
    $this->postJson(route('api.v1.borrower.auth.verify-otp'), [
        'phone' => '+260971000004',
        'otp' => '123456',
    ])->assertStatus(401);
});

// ─── PIN login ───────────────────────────────────────────────────────────────

test('borrower can login with correct pin', function () {
    $borrower = Borrower::factory()->create([
        'phone' => '+260971000010',
        'pin' => Hash::make('1234'),
        'is_active' => true,
    ]);

    $this->postJson(route('api.v1.borrower.auth.login-pin'), [
        'phone' => '+260971000010',
        'pin' => '1234',
    ])->assertStatus(200)
        ->assertJsonStructure(['data' => ['token']]);
});

test('borrower pin login fails with wrong pin', function () {
    Borrower::factory()->create([
        'phone' => '+260971000011',
        'pin' => Hash::make('1234'),
        'is_active' => true,
    ]);

    $this->postJson(route('api.v1.borrower.auth.login-pin'), [
        'phone' => '+260971000011',
        'pin' => '9999',
    ])->assertStatus(401);
});

test('inactive borrower cannot login', function () {
    Borrower::factory()->create([
        'phone' => '+260971000012',
        'pin' => Hash::make('1234'),
        'is_active' => false,
    ]);

    $this->postJson(route('api.v1.borrower.auth.login-pin'), [
        'phone' => '+260971000012',
        'pin' => '1234',
    ])->assertStatus(403);
});

// ─── PIN management ───────────────────────────────────────────────────────────

test('authenticated borrower can set a pin', function () {
    $borrower = Borrower::factory()->create([
        'phone' => '+260971000020',
        'is_active' => true,
        'pin' => null,
    ]);

    $this->actingAs($borrower, 'borrower')
        ->postJson(route('api.v1.borrower.auth.set-pin'), ['pin' => '5678'])
        ->assertStatus(200);

    expect(Hash::check('5678', $borrower->fresh()->pin))->toBeTrue();
});

test('unauthenticated borrower cannot set a pin', function () {
    $this->postJson(route('api.v1.borrower.auth.set-pin'), ['pin' => '5678'])
        ->assertStatus(401);
});
