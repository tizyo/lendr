<?php

namespace App\Services\Mail;

use App\Models\Landlord\EmailLog;
use App\Models\Landlord\PlatformEmailConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Asks Resend what happened to emails it accepted (GET /emails/{id} → last_event:
 * delivered, bounced, delivery_delayed, complained, opened, …). Checked on demand
 * from the landlord's tenant page, so no webhook has to be set up in Resend.
 */
class EmailDeliveryStatus
{
    /** Resend allows a couple of requests a second; stay under it. */
    private const PAUSE_MICROSECONDS = 550_000;

    /** @param  Collection<int, EmailLog>  $logs */
    public function refreshMany(Collection $logs, int $limit = 8): int
    {
        $checked = 0;

        foreach ($logs->filter->needsStatusCheck()->take($limit) as $log) {
            if ($checked > 0) {
                usleep(self::PAUSE_MICROSECONDS);
            }

            $this->refresh($log);
            $checked++;
        }

        return $checked;
    }

    public function refresh(EmailLog $log): EmailLog
    {
        $key = $this->apiKey();

        if (! $log->isTracked() || ! $key) {
            return $log;
        }

        try {
            $response = Http::withToken($key)->acceptJson()->timeout(10)
                ->get('https://api.resend.com/emails/'.urlencode($log->provider_id));
        } catch (\Throwable $e) {
            report($e);

            return $log;
        }

        $event = $response->successful() ? $response->json('last_event') : null;

        $log->forceFill([
            'status' => is_string($event) && $event !== '' ? $event : $log->status,
            'status_checked_at' => now(),
        ])->save();

        return $log;
    }

    private function apiKey(): ?string
    {
        $config = rescue(fn () => PlatformEmailConfig::active(), null, false);

        if ($config?->usesResend() && $config->isConfigured()) {
            return $config->api_key;
        }

        return config('mail.mailers.resend.key') ?: config('services.resend.key');
    }
}
