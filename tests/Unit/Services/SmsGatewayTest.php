<?php

use App\Services\SMS\Drivers\AfrikasTalkingDriver;
use App\Services\SMS\Drivers\ClickatellDriver;
use App\Services\SMS\Drivers\NullDriver;
use App\Services\SMS\Drivers\SmsToDriver;
use App\Services\SMS\Drivers\TwilioDriver;
use Illuminate\Support\Facades\Http;

// ─── NullDriver ───────────────────────────────────────────────────────────────

test('null driver always returns true', function () {
    $driver = new NullDriver;
    expect($driver->send('+260971000000', 'Test message'))->toBeTrue();
});

// ─── SmsToDriver ─────────────────────────────────────────────────────────────

test('smsto driver returns true on successful api response', function () {
    Http::fake([
        'https://api.sms.to/sms/send' => Http::response(['success' => true, 'message_id' => 'abc123'], 200),
    ]);

    $driver = new SmsToDriver(apiKey: 'test-key', senderId: 'LENDR');
    expect($driver->send('+260971000000', 'Hello'))->toBeTrue();
});

test('smsto driver returns false on api error', function () {
    Http::fake([
        'https://api.sms.to/sms/send' => Http::response(['success' => false, 'message' => 'Invalid key'], 401),
    ]);

    $driver = new SmsToDriver(apiKey: 'bad-key', senderId: 'LENDR');
    expect($driver->send('+260971000000', 'Hello'))->toBeFalse();
});

// ─── ClickatellDriver ─────────────────────────────────────────────────────────

test('clickatell driver returns true on successful api response', function () {
    Http::fake([
        'https://platform.clickatell.com/messages/http/send' => Http::response([
            'messages' => [
                ['accepted' => true, 'messageId' => 'msg001'],
            ],
        ], 200),
    ]);

    $driver = new ClickatellDriver(apiKey: 'test-api-key', senderId: 'LENDR');
    expect($driver->send('+260971000000', 'Hello'))->toBeTrue();
});

test('clickatell driver returns false when message is rejected', function () {
    Http::fake([
        'https://platform.clickatell.com/messages/http/send' => Http::response([
            'messages' => [
                ['accepted' => false, 'error' => 'Number blocked'],
            ],
        ], 200),
    ]);

    $driver = new ClickatellDriver(apiKey: 'test-api-key', senderId: 'LENDR');
    expect($driver->send('+260971000000', 'Hello'))->toBeFalse();
});

test('clickatell driver returns false on http error', function () {
    Http::fake([
        'https://platform.clickatell.com/messages/http/send' => Http::response([], 500),
    ]);

    $driver = new ClickatellDriver(apiKey: 'test-api-key', senderId: 'LENDR');
    expect($driver->send('+260971000000', 'Hello'))->toBeFalse();
});

// ─── AfrikasTalkingDriver ─────────────────────────────────────────────────────

test('africas talking driver returns true on success', function () {
    Http::fake([
        '*africastalking.com*' => Http::response([
            'SMSMessageData' => [
                'Recipients' => [
                    ['statusCode' => 101, 'status' => 'Success', 'number' => '+260971000000'],
                ],
            ],
        ], 200),
    ]);

    $driver = new AfrikasTalkingDriver(
        apiKey:   'test-key',
        username: 'sandbox',
        senderId: 'LENDR',
        sandbox:  true,
    );

    expect($driver->send('+260971000000', 'Hello'))->toBeTrue();
});

test('africas talking driver returns false on delivery failure', function () {
    Http::fake([
        '*africastalking.com*' => Http::response([
            'SMSMessageData' => [
                'Recipients' => [
                    ['statusCode' => 402, 'status' => 'InvalidPhoneNumber', 'number' => '+260971000000'],
                ],
            ],
        ], 200),
    ]);

    $driver = new AfrikasTalkingDriver(
        apiKey:   'test-key',
        username: 'sandbox',
        senderId: 'LENDR',
        sandbox:  true,
    );

    expect($driver->send('+260971000000', 'Hello'))->toBeFalse();
});

// ─── TwilioDriver E.164 normalisation ─────────────────────────────────────────

test('twilio driver normalises zambian number formats to e164', function () {
    // We can test the normalisation logic indirectly via reflection
    $driver = new TwilioDriver(sid: 'ACtest', authToken: 'token', fromNumber: '+12125551234');

    $reflect = new ReflectionClass($driver);
    $method  = $reflect->getMethod('formatE164');
    $method->setAccessible(true);

    expect($method->invoke($driver, '0971234567'))->toBe('+260971234567')
        ->and($method->invoke($driver, '260971234567'))->toBe('+260971234567')
        ->and($method->invoke($driver, '+260971234567'))->toBe('+260971234567');
});
