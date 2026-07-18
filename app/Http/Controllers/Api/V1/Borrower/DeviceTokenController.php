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
     * POST /me/device-tokens
     * Register a device token for push notifications.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:fcm,apns'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $borrower = $request->user(); // Borrower from Sanctum token

        $token = $this->push->register(
            $borrower,
            $data['token'],
            $data['platform'],
            $data['device_name'] ?? null,
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
