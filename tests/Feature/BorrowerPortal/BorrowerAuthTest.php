<?php

use App\Jobs\SendOtpSmsJob;
use App\Models\Tenant\Borrower;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

// ─── Request OTP ─────────────────────────────────────────────────────────────

test('borrower can request OTP for existing phone', function () {
    Queue::fake();
    $borrower = Borrower::factory()->create(['phone' => '0971234567', 'is_active' => true, 'is_blacklisted' => false]);

    $this->postJson(route('api.v1.borrower.auth.request-otp'), ['phone' => '0971234567'])
        ->assertOk()
        ->assertJsonPath('message', 'OTP sent to your phone number.');

    Queue::assertPushed(SendOtpSmsJob::class, fn ($job) => str_contains(serialize($job), '0971234567'));
})->group('borrower-auth');

test('borrower can request OTP and creates new borrower record for unknown phone', function () {
    Queue::fake();

    $this->postJson(route('api.v1.borrower.auth.request-otp'), ['phone' => '0971234999'])
        ->assertOk();

    $this->assertDatabaseHas('borrowers', ['phone' => '0971234999']);
})->group('borrower-auth');

test('request OTP fails for invalid phone number', function () {
    $this->postJson(route('api.v1.borrower.auth.request-otp'), ['phone' => '12345'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
})->group('borrower-auth');

test('request OTP is rejected for blacklisted borrower', function () {
    Queue::fake();
    Borrower::factory()->create(['phone' => '0971111111', 'is_blacklisted' => true]);

    $this->postJson(route('api.v1.borrower.auth.request-otp'), ['phone' => '0971111111'])
        ->assertForbidden();
})->group('borrower-auth');

test('OTP phone normalises +260 prefix to 0', function () {
    Queue::fake();
    $borrower = Borrower::factory()->create(['phone' => '0971234567', 'is_active' => true, 'is_blacklisted' => false]);

    $this->postJson(route('api.v1.borrower.auth.request-otp'), ['phone' => '+260971234567'])
        ->assertOk();
})->group('borrower-auth');

// ─── Verify OTP ───────────────────────────────────────────────────────────────

test('borrower can verify OTP and receive token', function () {
    $borrower = Borrower::factory()->create([
        'phone'          => '0971234567',
        'otp'            => Hash::make('123456'),
        'otp_expires_at' => now()->addMinutes(5),
        'is_active'      => true,
        'is_blacklisted' => false,
    ]);

    $this->postJson(route('api.v1.borrower.auth.verify-otp'), [
        'phone' => '0971234567',
        'otp'   => '123456',
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'borrower']])
        ->assertJsonPath('data.borrower.phone', '0971234567');

    // OTP should be cleared after use
    $borrower->refresh();
    $this->assertNull($borrower->otp);
})->group('borrower-auth');

test('verify OTP fails with wrong OTP', function () {
    Borrower::factory()->create([
        'phone'          => '0971234567',
        'otp'            => Hash::make('123456'),
        'otp_expires_at' => now()->addMinutes(5),
    ]);

    $this->postJson(route('api.v1.borrower.auth.verify-otp'), [
        'phone' => '0971234567',
        'otp'   => '999999',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid OTP.');
})->group('borrower-auth');

test('verify OTP fails when OTP is expired', function () {
    Borrower::factory()->create([
        'phone'          => '0971234567',
        'otp'            => Hash::make('123456'),
        'otp_expires_at' => now()->subMinutes(10), // expired
    ]);

    $this->postJson(route('api.v1.borrower.auth.verify-otp'), [
        'phone' => '0971234567',
        'otp'   => '123456',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'OTP has expired. Please request a new one.');
})->group('borrower-auth');

// ─── PIN Auth ─────────────────────────────────────────────────────────────────

test('borrower can set a PIN when authenticated', function () {
    $borrower = Borrower::factory()->create(['is_active' => true]);

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.auth.set-pin'), [
            'pin'              => '1234',
            'pin_confirmation' => '1234',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'PIN set successfully.');

    $borrower->refresh();
    $this->assertTrue(Hash::check('1234', $borrower->pin));
})->group('borrower-auth');

test('set PIN fails when confirmation does not match', function () {
    $borrower = Borrower::factory()->create();

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.auth.set-pin'), [
            'pin'              => '1234',
            'pin_confirmation' => '5678',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin_confirmation']);
})->group('borrower-auth');

test('borrower can login with PIN', function () {
    $borrower = Borrower::factory()->create([
        'phone'          => '0971234567',
        'pin'            => Hash::make('4321'),
        'is_active'      => true,
        'is_blacklisted' => false,
    ]);

    $this->postJson(route('api.v1.borrower.auth.login-pin'), [
        'phone' => '0971234567',
        'pin'   => '4321',
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'borrower']]);
})->group('borrower-auth');

test('PIN login fails with wrong PIN', function () {
    Borrower::factory()->create([
        'phone' => '0971234567',
        'pin'   => Hash::make('4321'),
    ]);

    $this->postJson(route('api.v1.borrower.auth.login-pin'), [
        'phone' => '0971234567',
        'pin'   => '0000',
    ])
        ->assertUnauthorized();
})->group('borrower-auth');

test('PIN login is rejected for suspended borrower', function () {
    Borrower::factory()->create([
        'phone'          => '0971234567',
        'pin'            => Hash::make('4321'),
        'is_blacklisted' => true,
        'is_active'      => true,
    ]);

    $this->postJson(route('api.v1.borrower.auth.login-pin'), [
        'phone' => '0971234567',
        'pin'   => '4321',
    ])
        ->assertForbidden();
})->group('borrower-auth');
