<?php

namespace App\Services\SMS\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Clickatell SMS driver (REST API v3).
 * Credentials: CLICKATELL_API_KEY
 * Docs: https://docs.clickatell.com/channels/sms-channels/sms-api-reference/
 */
class ClickatellDriver
{
    private const API_URL = 'https://platform.clickatell.com/messages/http/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderId = 'LENDR',
    ) {}

    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post(self::API_URL, [
                'messages' => [
                    [
                        'channel'  => 'sms',
                        'to'       => $phone,
                        'content'  => $message,
                        'from'     => $this->senderId,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('[SMS:Clickatell] Send failed', [
                    'phone'  => $phone,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $data     = $response->json();
            $messages = data_get($data, 'messages', []);

            foreach ($messages as $msg) {
                $accepted = data_get($msg, 'accepted', false);
                if (! $accepted) {
                    Log::warning('[SMS:Clickatell] Message rejected', ['phone' => $phone, 'msg' => $msg]);
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[SMS:Clickatell] Exception', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);

            return false;
        }
    }
}
