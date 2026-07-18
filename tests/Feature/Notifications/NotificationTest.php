<?php

use App\Enums\UserRole;
use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\User;

function notifUser(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

function makeNotification(User $user, array $attrs = []): InAppNotification
{
    return InAppNotification::create(array_merge([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Test Notification',
        'body' => 'This is a test.',
    ], $attrs));
}

// ─── Index ────────────────────────────────────────────────────────────────────

test('notification list returns notifications for authenticated user', function () {
    $user = notifUser();
    makeNotification($user);
    makeNotification($user);

    $other = notifUser();
    makeNotification($other); // should NOT appear

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.notifications.index'));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['notifications', 'unread_count']]);

    expect($response->json('data.notifications'))->toHaveCount(2);
});

test('unread_count reflects only unread notifications', function () {
    $user = notifUser();
    makeNotification($user);                                  // unread
    makeNotification($user, ['read_at' => now()]);            // read

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.notifications.index'));

    expect($response->json('data.unread_count'))->toBe(1);
});

test('notification structure includes required fields', function () {
    $user = notifUser();
    makeNotification($user);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.notifications.index'));

    $notification = $response->json('data.notifications.0');
    expect($notification)->toHaveKeys(['id', 'type', 'title', 'body', 'is_read', 'read_at', 'created_at']);
});

// ─── Mark Read ────────────────────────────────────────────────────────────────

test('a notification can be marked as read', function () {
    $user = notifUser();
    $n = makeNotification($user);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.notifications.read', $n->id));

    $response->assertOk()
        ->assertJsonPath('data.is_read', true);

    expect($n->fresh()->read_at)->not->toBeNull();
});

test('marking an already-read notification is idempotent', function () {
    $user = notifUser();
    $n = makeNotification($user, ['read_at' => now()->subMinute()]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.notifications.read', $n->id))
        ->assertOk();
});

test('cannot mark another user notification as read', function () {
    $user = notifUser();
    $other = notifUser();
    $n = makeNotification($other);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.notifications.read', $n->id))
        ->assertStatus(404);
});

// ─── Mark All Read ────────────────────────────────────────────────────────────

test('mark all read sets read_at on all unread notifications', function () {
    $user = notifUser();
    makeNotification($user);
    makeNotification($user);
    makeNotification($user, ['read_at' => now()]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.notifications.read-all'));

    $response->assertOk()
        ->assertJsonPath('data.updated', 2);

    $unread = InAppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    expect($unread)->toBe(0);
});

test('mark all read only affects the authenticated user', function () {
    $user = notifUser();
    $other = notifUser();
    makeNotification($user);
    makeNotification($other); // should NOT be touched

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.notifications.read-all'))
        ->assertOk();

    expect(InAppNotification::where('user_id', $other->id)->whereNull('read_at')->count())->toBe(1);
});
