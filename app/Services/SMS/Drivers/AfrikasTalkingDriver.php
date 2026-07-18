<?php

namespace App\Services\SMS\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Afrika's Talking SMS driver.
 * Credentials: sms_api_key and sms_sender_name from tenant settings.
 */
class AfrikasTalkingDriver
{
    private const API_URL = 'https://api.africastalking.com/version1/messaging';

    private const SANDBOX_URL = 'https://api.sandbox.africastalking.com/version1/messaging';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $username,
        private readonly string $senderId,
        private readonly bool $sandbox = false,
    ) {}

    public function send(string $phone, string $message): bool
    {
        $url = $this->sandbox ? self::SANDBOX_URL : self::API_URL;

        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->asForm()->post($url, [
                'username' => $this->username,
                'to' => $phone,
                'message' => $message,
                'from' => $this->senderId ?: null,
            ]);

            if (! $response->successful()) {
                Log::warning('[SMS:AfrikasTalking] Send failed', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $result = $response->json();
            $recipients = data_get($result, 'SMSMessageData.Recipients', []);

            foreach ($recipients as $recipient) {
                if (($recipient['statusCode'] ?? 0) !== 101) {
                    Log::warning('[SMS:AfrikasTalking] Delivery failed', $recipient);

                    return false;
                }
            }

            return true;

        } catch (\Throwable $e) {
            Log::error('[SMS:AfrikasTalking] Exception', ['error' => $e->getMessage(), 'phone' => $phone]);

            return false;
        }
    }
}
