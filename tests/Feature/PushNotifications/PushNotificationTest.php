<?php

use App\Models\Tenant\Borrower;
use App\Models\Tenant\DeviceToken;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Http;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function pushBorrower(): Borrower
{
    return Borrower::factory()->create(['is_active' => true]);
}

// ─── Service unit tests ───────────────────────────────────────────────────────

test('register creates a device token record', function () {
    $borrower = pushBorrower();
    $svc = app(PushNotificationService::class);

    $token = $svc->register($borrower, 'token-abc-123', 'fcm', 'Test Phone');

    expect($token->id)->toBeInt()
        ->and($token->token)->toBe('token-abc-123')
        ->and($token->platform)->toBe('fcm')
        ->and($token->is_active)->toBeTrue();
});

test('register is idempotent — same token updates rather than duplicates', function () {
    $borrower = pushBorrower();
    $svc = app(PushNotificationService::class);

    $svc->register($borrower, 'token-dup', 'fcm');
    $svc->register($borrower, 'token-dup', 'fcm', 'Updated name');

    $count = DeviceToken::where('borrower_id', $borrower->id)->where('token', 'token-dup')->count();
    expect($count)->toBe(1);
});

test('unregister deactivates a device token', function () {
    $borrower = pushBorrower();
    $svc = app(PushNotificationService::class);

    $svc->register($borrower, 'token-to-remove', 'fcm');
    $svc->unregister($borrower, 'token-to-remove');

    $token = DeviceToken::where('borrower_id', $borrower->id)->where('token', 'token-to-remove')->first();
    expect($token->is_active)->toBeFalse();
});

test('sendToBorrower returns 0 when no active tokens', function () {
    $borrower = pushBorrower();
    $svc = app(PushNotificationService::class);

    $sent = $svc->sendToBorrower($borrower, 'Hello', 'World');
    expect($sent)->toBe(0);
});

test('sendToBorrower skips inactive tokens', function () {
    $borrower = pushBorrower();
    DeviceToken::create([
        'borrower_id' => $borrower->id,
        'token' => 'inactive-token',
        'platform' => 'fcm',
        'is_active' => false,
    ]);

    Http::fake(); // should not be called

    $svc = app(PushNotificationService::class);
    $sent = $svc->sendToBorrower($borrower, 'Test', 'Body');
    expect($sent)->toBe(0);

    Http::assertNothingSent();
});

test('sendToBorrower attempts FCM when server key configured', function () {
    $borrower = pushBorrower();
    DeviceToken::create([
        'borrower_id' => $borrower->id,
        'token' => 'active-token-fcm',
        'platform' => 'fcm',
        'is_active' => true,
    ]);

    // Fake FCM success response
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['success' => 1], 200),
    ]);

    config(['services.fcm.server_key' => 'test-fcm-key']);

    $svc = app(PushNotificationService::class);
    $sent = $svc->sendToBorrower($borrower, 'Loan Update', 'Your payment was received.');
    expect($sent)->toBe(1);
});

test('sendToBorrower returns 0 without FCM key configured', function () {
    $borrower = pushBorrower();
    DeviceToken::create([
        'borrower_id' => $borrower->id,
        'token' => 'active-no-key',
        'platform' => 'fcm',
        'is_active' => true,
    ]);

    config(['services.fcm.server_key' => null]);

    Http::fake();
    $svc = app(PushNotificationService::class);
    $sent = $svc->sendToBorrower($borrower, 'Test', 'Body');
    expect($sent)->toBe(0);
});

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('POST me/device-tokens registers a token for borrower', function () {
    $borrower = pushBorrower();
    $token = $borrower->createToken('portal')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.v1.borrower.device-tokens.register'), [
            'token' => 'device-abc-456',
            'platform' => 'fcm',
            'device_name' => 'My Phone',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.token_id', fn ($v) => is_int($v));
});

test('DELETE me/device-tokens unregisters a token', function () {
    $borrower = pushBorrower();
    DeviceToken::create([
        'borrower_id' => $borrower->id,
        'token' => 'token-to-unregister',
        'platform' => 'fcm',
        'is_active' => true,
    ]);

    $token = $borrower->createToken('portal')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson(route('api.v1.borrower.device-tokens.unregister'), [
            'token' => 'token-to-unregister',
        ])
        ->assertOk();

    expect(DeviceToken::where('token', 'token-to-unregister')->value('is_active'))->toBeFalse();
});

test('unauthenticated cannot register device tokens', function () {
    $this->postJson(route('api.v1.borrower.device-tokens.register'), [
        'token' => 'x',
        'platform' => 'fcm',
    ])->assertStatus(401);
});
