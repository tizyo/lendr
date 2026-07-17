<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM / APNs push notification service.
 *
 * Requires tenant setting:
 *   fcm_server_key  — Firebase Cloud Messaging server key (FCM v1 or legacy)
 *   apns_key_id     — Apple Push Notification service key ID
 *
 * Failures are always caught and logged — push errors must never block business logic.
 */
class PushNotificationService
{
    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Send a push notification to all active devices of a borrower.
     *
     * @param  array<string, mixed> $data  Extra payload (e.g. ['loan_id' => 1])
     */
    public function sendToBorrower(Borrower $borrower, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::where('borrower_id', $borrower->id)
            ->where('is_active', true)
            ->pluck('token', 'id');

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($tokens as $tokenId => $token) {
            try {
                $success = $this->dispatch($token, $title, $body, $data);
                if ($success) {
                    DeviceToken::where('id', $tokenId)->update(['last_used_at' => now()]);
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning("Push notification failed for token #{$tokenId}: " . $e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * Register or refresh a device token for a borrower.
     */
    public function register(Borrower $borrower, string $token, string $platform = 'fcm', ?string $deviceName = null): DeviceToken
    {
        return DeviceToken::updateOrCreate(
            ['borrower_id' => $borrower->id, 'token' => $token],
            ['platform' => $platform, 'device_name' => $deviceName, 'is_active' => true],
        );
    }

    /**
     * Deactivate a device token.
     */
    public function unregister(Borrower $borrower, string $token): bool
    {
        return (bool) DeviceToken::where('borrower_id', $borrower->id)
            ->where('token', $token)
            ->update(['is_active' => false]);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function dispatch(string $token, string $title, string $body, array $data): bool
    {
        $serverKey = config('services.fcm.server_key');

        if (! $serverKey) {
            // No FCM key configured — log and skip (non-fatal)
            Log::debug('FCM server key not configured; push notification skipped.');
            return false;
        }

        $payload = [
            'to'           => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
            ],
            'data' => $data,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type'  => 'application/json',
        ])->post(self::FCM_ENDPOINT, $payload);

        if ($response->failed()) {
            Log::warning('FCM push failed: ' . $response->body());
            return false;
        }

        $json = $response->json();

        // FCM returns success=1 in the JSON body
        return isset($json['success']) && $json['success'] === 1;
    }
}
