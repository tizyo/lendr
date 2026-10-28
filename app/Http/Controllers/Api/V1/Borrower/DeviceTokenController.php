<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends BaseApiController
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * GET /me/push/config
     * What the PWA needs to subscribe this browser to push notifications.
     */
    public function config(): JsonResponse
    {
        return $this->success([
            'enabled' => $this->push->isConfigured(),
            'public_key' => config('services.webpush.public_key'),
        ]);
    }

    /**
     * POST /me/device-tokens
     * Register a device for push notifications. For the PWA (platform=webpush)
     * `token` is the PushSubscription endpoint and `keys` its encryption keys.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:webpush,fcm,apns'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'keys.p256dh' => ['required_if:platform,webpush', 'nullable', 'string', 'max:255'],
            'keys.auth' => ['required_if:platform,webpush', 'nullable', 'string', 'max:255'],
        ]);

        if ($data['platform'] === 'webpush' && ! str_starts_with($data['token'], 'https://')) {
            return $this->error('Invalid push subscription endpoint.', 422);
        }

        $borrower = $request->user(); // Borrower from Sanctum token

        $token = $this->push->register(
            $borrower,
            $data['token'],
            $data['platform'],
            $data['device_name'] ?? null,
            $data['keys']['p256dh'] ?? null,
            $data['keys']['auth'] ?? null,
        );

        return $this->success(['token_id' => $token->id], 'Device registered.', 201);
    }

    /**
     * DELETE /me/device-tokens
     * Unregister a device token.
     */
    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $borrower = $request->user();
        $this->push->unregister($borrower, $data['token']);

        return $this->success(null, 'Device unregistered.');
    }
}
