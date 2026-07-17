<?php

namespace App\Services\SMS\Drivers;

use Illuminate\Support\Facades\Log;

/**
 * No-op SMS driver — used when SMS is not configured.
 * Logs the message so developers can see OTPs locally.
 */
class NullDriver
{
    public function send(string $phone, string $message): bool
    {
        Log::info("[SMS:NullDriver] To: {$phone} | Message: {$message}");

        return true;
    }
}
