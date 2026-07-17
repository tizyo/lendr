<?php

namespace App\Services\SMS\Drivers;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

/**
 * Twilio SMS driver.
 * Credentials: TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER
 */
class TwilioDriver
{
    public function __construct(
        private readonly string $sid,
        private readonly string $authToken,
        private readonly string $fromNumber,
    ) {}

    public function send(string $phone, string $message): bool
    {
        try {
            $client = new TwilioClient($this->sid, $this->authToken);

            $client->messages->create(
                $this->formatE164($phone),
                [
                    'from' => $this->fromNumber,
                    'body' => $message,
                ]
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('[SMS:Twilio] Exception', [
                'error' => $e->getMessage(),
                'phone' => $phone,
            ]);

            return false;
        }
    }

    /**
     * Normalise to E.164 for Zambian numbers.
     * Accepts: 0971234567 | +260971234567 | 260971234567
     */
    private function formatE164(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '260')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '+260' . substr($phone, 1);
        }

        return '+' . $phone;
    }
}
