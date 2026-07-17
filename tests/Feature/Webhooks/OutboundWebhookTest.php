<?php

use App\Enums\UserRole;
use App\Jobs\DeliverWebhookJob;
use App\Models\Tenant\WebhookDelivery;
use App\Models\Tenant\WebhookEndpoint;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Queue;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function webhookAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function createEndpoint(array $attrs = []): WebhookEndpoint
{
    return WebhookEndpoint::create(array_merge([
        'url'         => 'https://example.com/webhook',
        'secret'      => 'test-secret-32-chars-long-12345678',
        'events'      => ['loan.created', 'payment.recorded'],
        'is_active'   => true,
        'failure_count' => 0,
    ], $attrs));
}

// ─── Endpoint CRUD ────────────────────────────────────────────────────────────

test('can create a webhook endpoint', function () {
    $admin = webhookAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.store'), [
            'url'    => 'https://myapp.com/hooks',
            'events' => ['loan.created', 'payment.recorded'],
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.endpoint.url', 'https://myapp.com/hooks');

    $this->assertDatabaseHas('webhook_endpoints', ['url' => 'https://myapp.com/hooks']);
})->group('webhooks');

test('endpoint is created with random secret', function () {
    $admin = webhookAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.store'), [
            'url'    => 'https://example.com/hook',
            'events' => ['loan.created'],
        ])
        ->assertStatus(201);

    expect($resp->json('data.endpoint.secret'))->toBeString()->not->toBeEmpty();
})->group('webhooks');

test('create endpoint requires valid url and events', function () {
    $admin = webhookAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.store'), [])
        ->assertJsonValidationErrors(['url', 'events']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.store'), ['url' => 'not-a-url', 'events' => ['loan.created']])
        ->assertJsonValidationErrors(['url']);
})->group('webhooks');

test('events must be from allowed list', function () {
    $admin = webhookAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.store'), [
            'url'    => 'https://example.com/hook',
            'events' => ['invalid.event'],
        ])
        ->assertJsonValidationErrors(['events.0']);
})->group('webhooks');

test('can list webhook endpoints', function () {
    $admin = webhookAdmin();
    createEndpoint(); createEndpoint(['url' => 'https://second.com/hook']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.webhook-endpoints.index'))
        ->assertOk();

    expect(count($resp->json('data.endpoints')))->toBe(2);
})->group('webhooks');

test('response includes available events list', function () {
    $admin = webhookAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.webhook-endpoints.index'))
        ->assertOk();

    expect($resp->json('data.available_events'))->toContain('loan.created');
})->group('webhooks');

test('can update endpoint', function () {
    $admin    = webhookAdmin();
    $endpoint = createEndpoint();

    $this->actingAs($admin)
        ->putJson(route('api.v1.webhook-endpoints.update', $endpoint), [
            'is_active' => false,
            'events'    => ['loan.created'],
        ])
        ->assertOk()
        ->assertJsonPath('data.endpoint.is_active', false);
})->group('webhooks');

test('can delete an endpoint', function () {
    $admin    = webhookAdmin();
    $endpoint = createEndpoint();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.webhook-endpoints.destroy', $endpoint))
        ->assertOk();

    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
})->group('webhooks');

test('can rotate endpoint secret', function () {
    $admin    = webhookAdmin();
    $endpoint = createEndpoint(['secret' => 'old-secret']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.rotate-secret', $endpoint))
        ->assertOk();

    $newSecret = $resp->json('data.secret');
    expect($newSecret)->not->toBe('old-secret');
    expect($endpoint->fresh()->secret)->toBe($newSecret);
})->group('webhooks');

// ─── Delivery dispatch ────────────────────────────────────────────────────────

test('dispatch service creates a delivery and queues job', function () {
    Queue::fake();
    $admin    = webhookAdmin();
    $endpoint = createEndpoint(['events' => ['loan.created']]);

    app(\App\Services\WebhookDispatchService::class)
        ->dispatch('loan.created', ['loan_number' => 'LN-001']);

    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_endpoint_id' => $endpoint->id,
        'event'               => 'loan.created',
        'status'              => 'pending',
    ]);

    Queue::assertPushed(DeliverWebhookJob::class);
})->group('webhooks');

test('dispatch only sends to endpoints subscribed to the event', function () {
    Queue::fake();

    $ep1 = createEndpoint(['events' => ['loan.created']]);
    $ep2 = createEndpoint(['url' => 'https://other.com', 'events' => ['payment.recorded']]);

    app(\App\Services\WebhookDispatchService::class)
        ->dispatch('loan.created', []);

    expect(WebhookDelivery::where('webhook_endpoint_id', $ep1->id)->count())->toBe(1);
    expect(WebhookDelivery::where('webhook_endpoint_id', $ep2->id)->count())->toBe(0);
})->group('webhooks');

test('dispatch skips inactive endpoints', function () {
    Queue::fake();
    createEndpoint(['is_active' => false, 'events' => ['loan.created']]);

    app(\App\Services\WebhookDispatchService::class)
        ->dispatch('loan.created', []);

    expect(WebhookDelivery::count())->toBe(0);
    Queue::assertNothingPushed();
})->group('webhooks');

test('can list deliveries for an endpoint', function () {
    $admin    = webhookAdmin();
    $endpoint = createEndpoint();

    WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'loan.created', 'payload' => [], 'status' => 'success', 'attempts' => 1]);
    WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'loan.created', 'payload' => [], 'status' => 'failed', 'attempts' => 3]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.webhook-endpoints.deliveries', $endpoint))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
})->group('webhooks');

test('deliveries can be filtered by status', function () {
    $admin    = webhookAdmin();
    $endpoint = createEndpoint();

    WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'loan.created', 'payload' => [], 'status' => 'success', 'attempts' => 1]);
    WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event' => 'loan.created', 'payload' => [], 'status' => 'failed', 'attempts' => 3]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.webhook-endpoints.deliveries', [$endpoint, 'status' => 'success']))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
})->group('webhooks');

test('can queue a retry for a failed delivery', function () {
    Queue::fake();
    $admin    = webhookAdmin();
    $endpoint = createEndpoint();

    $delivery = WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event'               => 'loan.created',
        'payload'             => [],
        'status'              => 'failed',
        'attempts'            => 3,
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.webhook-endpoints.deliveries.retry', [$endpoint, $delivery]))
        ->assertOk();

    Queue::assertPushed(DeliverWebhookJob::class);
    expect($delivery->fresh()->status)->toBe('pending');
    expect($delivery->fresh()->attempts)->toBe(0);
})->group('webhooks');

test('signature is computed correctly', function () {
    $endpoint = createEndpoint(['secret' => 'my-secret']);
    $payload  = '{"event":"test"}';

    $sig = $endpoint->sign($payload);
    expect($sig)->toBe(hash_hmac('sha256', $payload, 'my-secret'));
})->group('webhooks');

test('unauthenticated cannot access webhook endpoints', function () {
    $this->getJson(route('api.v1.webhook-endpoints.index'))->assertStatus(401);
})->group('webhooks');
