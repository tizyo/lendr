<?php

namespace App\Services\SMS\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS.to driver.
 * Docs: https://developers.sms.to
 */
class SmsToDriver
{
    private const API_URL = 'https://api.sms.to/sms/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderId = 'LENDR',
    ) {}

    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])->post(self::API_URL, [
                'to'        => $phone,
                'message'   => $message,
                'sender_id' => $this->senderId,
                'bypass_optout' => false,
            ]);

            if (! $response->successful()) {
                Log::warning('[SMS:SmsTo] Send failed', [
                    'phone'  => $phone,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            if (($data['success'] ?? false) === false) {
                Log::warning('[SMS:SmsTo] API error', ['phone' => $phone, 'response' => $data]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[SMS:SmsTo] Exception', ['error' => $e->getMessage(), 'phone' => $phone]);
            return false;
        }
    }
}
