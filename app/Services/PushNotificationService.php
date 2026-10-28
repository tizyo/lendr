<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\DeviceToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Push notifications to the borrower PWA via the Web Push standard (VAPID).
 *
 * Works in Chrome/Edge/Firefox and in iOS 16.4+ once the PWA is added to the
 * home screen. Needs services.webpush keys (php artisan lendr:webpush-keys).
 * Legacy fcm/apns tokens can't be delivered (Google shut the legacy FCM API
 * down in 2024) and are skipped.
 *
 * Failures are always caught and logged — push errors must never block business logic.
 */
class PushNotificationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * Send a push notification to all of a borrower's subscribed browsers.
     *
     * @param  array<string, mixed>  $data  Extra payload — `url` is opened when the notification is tapped
     */
    public function sendToBorrower(Borrower $borrower, string $title, string $body, array $data = []): int
    {
        if (! $this->isConfigured()) {
            Log::debug('Web Push (VAPID) keys not configured; push notification skipped.');

            return 0;
        }

        $subscriptions = DeviceToken::where('borrower_id', $borrower->id)
            ->where('is_active', true)
            ->where('platform', 'webpush')
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = json_encode(array_filter([
            'title' => $title,
            'body' => $body,
            'url' => $data['url'] ?? '/app/notifications',
            'tag' => $data['tag'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]));

        $sent = 0;

        try {
            $byEndpoint = $subscriptions->keyBy('token');

            foreach ($this->deliver($subscriptions, $payload) as $report) {
                $device = $byEndpoint->get($report->getEndpoint());

                if ($report->isSuccess()) {
                    $device?->update(['last_used_at' => now()]);
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    // 404 / 410: the browser unsubscribed or the app was removed — stop trying.
                    $device?->update(['is_active' => false]);
                } else {
                    Log::warning('Web Push delivery failed: '.$report->getReason(), ['device_token_id' => $device?->id]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Web Push failed: '.$e->getMessage(), ['borrower_id' => $borrower->id]);
        }

        return $sent;
    }

    /**
     * Register or refresh a browser push subscription (or a legacy device token).
     */
    public function register(
        Borrower $borrower,
        string $token,
        string $platform = 'webpush',
        ?string $deviceName = null,
        ?string $publicKey = null,
        ?string $authToken = null,
    ): DeviceToken {
        return DeviceToken::updateOrCreate(
            ['borrower_id' => $borrower->id, 'token' => $token],
            [
                'platform' => $platform,
                'device_name' => $deviceName,
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'is_active' => true,
            ],
        );
    }

    /**
     * Deactivate a device token / push subscription.
     */
    public function unregister(Borrower $borrower, string $token): bool
    {
        return (bool) DeviceToken::where('borrower_id', $borrower->id)
            ->where('token', $token)
            ->update(['is_active' => false]);
    }

    /**
     * Encrypts and sends the payload to each subscription's push service.
     *
     * @param  Collection<int, DeviceToken>  $devices
     * @return iterable<MessageSentReport>
     */
    protected function deliver(Collection $devices, string $payload): iterable
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ], ['TTL' => 86400, 'urgency' => 'normal']);

        foreach ($devices as $device) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $device->token,
                'publicKey' => $device->public_key,
                'authToken' => $device->auth_token,
            ]), $payload);
        }

        return $webPush->flush();
    }
}
