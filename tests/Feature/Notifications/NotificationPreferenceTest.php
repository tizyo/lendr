<?php

use App\Enums\UserRole;
use App\Models\Tenant\NotificationPreference;
use App\Models\Tenant\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function prefUser(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

// ─── Index ────────────────────────────────────────────────────────────────────

test('user can view their notification preference matrix', function () {
    $user = prefUser();

    $this->actingAs($user)
        ->getJson(route('api.v1.notification-preferences.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['channels', 'events', 'matrix']]);
})->group('notification-preferences');

test('matrix includes all channels and events', function () {
    $user = prefUser();

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.notification-preferences.index'))
        ->assertOk();

    $channels = $response->json('data.channels');
    $events = $response->json('data.events');

    expect($channels)->toContain('in_app', 'email', 'sms');
    expect($events)->toContain('loan_status_change', 'payment_recorded', 'overdue_reminder');
})->group('notification-preferences');

test('matrix defaults all preferences to enabled when none are stored', function () {
    $user = prefUser();

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.notification-preferences.index'))
        ->assertOk();

    $matrix = $response->json('data.matrix');

    foreach (NotificationPreference::channels() as $channel) {
        foreach (NotificationPreference::events() as $event) {
            $this->assertTrue($matrix[$channel][$event], "Expected {$channel}.{$event} to be true by default");
        }
    }
})->group('notification-preferences');

test('stored preferences override defaults in matrix', function () {
    $user = prefUser();
    NotificationPreference::create([
        'user_id' => $user->id,
        'channel' => 'email',
        'event' => 'overdue_reminder',
        'is_enabled' => false,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.notification-preferences.index'))
        ->assertOk();

    $this->assertFalse($response->json('data.matrix.email.overdue_reminder'));
    $this->assertTrue($response->json('data.matrix.in_app.overdue_reminder')); // default still true
})->group('notification-preferences');

test('unauthenticated user cannot view preferences', function () {
    $this->getJson(route('api.v1.notification-preferences.index'))
        ->assertUnauthorized();
})->group('notification-preferences');

// ─── Update ───────────────────────────────────────────────────────────────────

test('user can update a single notification preference', function () {
    $user = prefUser();

    $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [
                ['channel' => 'email', 'event' => 'overdue_reminder', 'is_enabled' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Preferences updated.');

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'channel' => 'email',
        'event' => 'overdue_reminder',
        'is_enabled' => 0,
    ]);
})->group('notification-preferences');

test('user can update multiple preferences at once', function () {
    $user = prefUser();

    $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [
                ['channel' => 'email',  'event' => 'overdue_reminder',   'is_enabled' => false],
                ['channel' => 'sms',    'event' => 'payment_reminder',   'is_enabled' => false],
                ['channel' => 'in_app', 'event' => 'loan_status_change', 'is_enabled' => true],
            ],
        ])
        ->assertOk();

    $this->assertSame(3, NotificationPreference::where('user_id', $user->id)->count());
})->group('notification-preferences');

test('updating a preference twice keeps only one DB row', function () {
    $user = prefUser();

    $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [['channel' => 'email', 'event' => 'broadcast', 'is_enabled' => false]],
        ]);

    $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [['channel' => 'email', 'event' => 'broadcast', 'is_enabled' => true]],
        ]);

    $this->assertSame(1, NotificationPreference::where([
        'user_id' => $user->id,
        'channel' => 'email',
        'event' => 'broadcast',
    ])->count());

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'channel' => 'email',
        'event' => 'broadcast',
        'is_enabled' => 1,
    ]);
})->group('notification-preferences');

test('update returns the updated matrix', function () {
    $user = prefUser();

    $response = $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [
                ['channel' => 'sms', 'event' => 'payment_recorded', 'is_enabled' => false],
            ],
        ])
        ->assertOk();

    $this->assertFalse($response->json('data.matrix.sms.payment_recorded'));
})->group('notification-preferences');

test('update rejects invalid channel', function () {
    $user = prefUser();

    $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [
                ['channel' => 'push', 'event' => 'broadcast', 'is_enabled' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preferences.0.channel']);
})->group('notification-preferences');

test('update rejects invalid event', function () {
    $user = prefUser();

    $this->actingAs($user)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [
                ['channel' => 'email', 'event' => 'unknown_event', 'is_enabled' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preferences.0.event']);
})->group('notification-preferences');

test('preferences are isolated per user', function () {
    $user1 = prefUser();
    $user2 = prefUser();

    $this->actingAs($user1)
        ->putJson(route('api.v1.notification-preferences.update'), [
            'preferences' => [['channel' => 'email', 'event' => 'broadcast', 'is_enabled' => false]],
        ]);

    // User2 should still see default (true)
    $response = $this->actingAs($user2)
        ->getJson(route('api.v1.notification-preferences.index'))
        ->assertOk();

    $this->assertTrue($response->json('data.matrix.email.broadcast'));
})->group('notification-preferences');
