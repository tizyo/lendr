<?php

use App\Jobs\SendBorrowerPushJob;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\DeviceToken;
use App\Services\PushNotificationService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * A fixed throwaway VAPID pair (P-256, base64url). Generating keys at test time
 * needs OpenSSL EC support, which Windows PHP lacks without an OPENSSL_CONF.
 */
function withVapidKeys(): void
{
    config([
        'services.webpush.public_key' => 'BLty1Qy_wCPi6gaZKClz7ctnzYhb4GhOq-PqkX72N7iXvI5fv8G8aTXWfH1ioZE796tru-vDqLBmQbKrXz78kUs',
        'services.webpush.private_key' => 'SS0A79R_bj57N9wXmvLgVeGNnSFW8NU8TmAIWwBc43A',
        'services.webpush.subject' => 'mailto:test@lendr.test',
    ]);
}

function webPushSubscription(Borrower $borrower, string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): DeviceToken
{
    return DeviceToken::create([
        'borrower_id' => $borrower->id,
        'platform' => 'webpush',
        'token' => $endpoint,
        'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
        'auth_token' => 'tBHItJI5svbpez7KI4CCXg',
        'is_active' => true,
    ]);
}

/**
 * Replace real delivery with a recorder that reports each push as delivered
 * (or as an expired subscription, i.e. HTTP 410 from the push service).
 */
function fakeWebPush(bool $expired = false): PushNotificationService
{
    $fake = new class($expired) extends PushNotificationService
    {
        public array $queued = [];

        public function __construct(private bool $expired) {}

        protected function deliver(Collection $devices, string $payload): iterable
        {
            foreach ($devices as $device) {
                $this->queued[] = ['endpoint' => $device->token, 'payload' => json_decode($payload, true)];

                yield new MessageSentReport(
                    new Request('POST', $device->token),
                    $this->expired ? new Response(410) : new Response(201),
                    ! $this->expired,
                    $this->expired ? 'Gone' : 'OK',
                );
            }
        }
    };

    app()->instance(PushNotificationService::class, $fake);

    return $fake;
}

// ─── Subscribing (borrower API) ──────────────────────────────────────────────

it('gives the PWA the VAPID public key it needs to subscribe', function () {
    withVapidKeys();
    $borrower = Borrower::factory()->create();

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.push.config'))
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.public_key', config('services.webpush.public_key'));
});

it('reports push as disabled until VAPID keys are configured', function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

    $this->actingAs(Borrower::factory()->create(), 'sanctum')
        ->getJson(route('api.v1.borrower.push.config'))
        ->assertJsonPath('data.enabled', false);
});

it('stores a browser push subscription with its keys', function () {
    $borrower = Borrower::factory()->create();

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.device-tokens.register'), [
            'platform' => 'webpush',
            'token' => 'https://fcm.googleapis.com/fcm/send/xyz',
            'keys' => ['p256dh' => 'pub-key', 'auth' => 'auth-secret'],
            'device_name' => 'Chrome on Android',
        ])
        ->assertCreated();

    $device = DeviceToken::where('borrower_id', $borrower->id)->sole();

    expect($device->platform)->toBe('webpush')
        ->and($device->public_key)->toBe('pub-key')
        ->and($device->auth_token)->toBe('auth-secret')
        ->and($device->toArray())->not->toHaveKey('auth_token');
});

it('requires the subscription keys for web push', function () {
    $this->actingAs(Borrower::factory()->create(), 'sanctum')
        ->postJson(route('api.v1.borrower.device-tokens.register'), [
            'platform' => 'webpush',
            'token' => 'https://fcm.googleapis.com/fcm/send/xyz',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

// ─── Delivery ────────────────────────────────────────────────────────────────

it('queues a push whenever a borrower gets an in-app notification', function () {
    withVapidKeys();
    Queue::fake();
    $borrower = Borrower::factory()->create();

    BorrowerNotification::create(['borrower_id' => $borrower->id, 'type' => 'disbursed', 'title' => 'Funds sent', 'body' => 'x', 'data' => ['loan_id' => 7]]);

    Queue::assertPushed(SendBorrowerPushJob::class);
});

it('does not queue pushes when push is not configured', function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
    Queue::fake();

    BorrowerNotification::create(['borrower_id' => Borrower::factory()->create()->id, 'type' => 'overdue', 'title' => 't', 'body' => 'b']);

    Queue::assertNotPushed(SendBorrowerPushJob::class);
});

it('sends the notification to the borrower\'s phone and opens the loan when tapped', function () {
    withVapidKeys();
    $fake = fakeWebPush();
    $borrower = Borrower::factory()->create();
    $device = webPushSubscription($borrower);

    BorrowerNotification::create(['borrower_id' => $borrower->id, 'type' => 'disbursed', 'title' => 'Funds sent', 'body' => 'Check your wallet', 'data' => ['loan_id' => 7]]);

    expect($fake->queued)->toHaveCount(1)
        ->and($fake->queued[0]['endpoint'])->toBe($device->token)
        ->and($fake->queued[0]['payload'])->toMatchArray(['title' => 'Funds sent', 'body' => 'Check your wallet', 'url' => '/app/loans/7'])
        ->and($device->fresh()->last_used_at)->not->toBeNull();
});

it('stops pushing to a subscription the browser has expired', function () {
    withVapidKeys();
    fakeWebPush(expired: true);
    $borrower = Borrower::factory()->create();
    $device = webPushSubscription($borrower);

    app(PushNotificationService::class)->sendToBorrower($borrower, 'Hi', 'There');

    expect($device->fresh()->is_active)->toBeFalse();
});

it('skips legacy FCM tokens, which can no longer be delivered', function () {
    withVapidKeys();
    $fake = fakeWebPush();
    $borrower = Borrower::factory()->create();
    DeviceToken::create(['borrower_id' => $borrower->id, 'platform' => 'fcm', 'token' => 'legacy-token', 'is_active' => true]);

    expect(app(PushNotificationService::class)->sendToBorrower($borrower, 'Hi', 'There'))->toBe(0)
        ->and($fake->queued)->toBeEmpty();
});

// ─── Installable PWA ─────────────────────────────────────────────────────────

it('serves a tenant-branded web app manifest', function () {
    DB::table('settings')->insert([
        ['key' => 'pwa_app_name', 'value' => 'Vozara Loans', 'updated_at' => now()],
        ['key' => 'pwa_theme_color', 'value' => '#7C3AED', 'updated_at' => now()],
    ]);

    $this->get('/app/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Vozara Loans')
        ->assertJsonPath('theme_color', '#7C3AED')
        ->assertJsonPath('scope', '/app/')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonCount(4, 'icons');
});

it('serves the service worker with an /app/ scope', function () {
    $this->get('/app/sw.js')
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/app/')
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
});

it('generates square PNG home-screen icons', function (int $size, string $purpose) {
    $response = $this->get("/app/icons/{$size}-{$purpose}.png")->assertOk()->assertHeader('Content-Type', 'image/png');

    [$width, $height] = getimagesizefromstring($response->getContent());

    expect([$width, $height])->toBe([$size, $size]);
})->with([[192, 'any'], [512, 'maskable'], [180, 'any']]);

it('rejects icon sizes it does not generate', function () {
    $this->get('/app/icons/4096-any.png')->assertNotFound();
});
