<?php

namespace App\Services\SMS;

use App\Models\Landlord\PlatformSmsConfig;
use App\Services\SMS\Drivers\AfrikasTalkingDriver;
use App\Services\SMS\Drivers\ClickatellDriver;
use App\Services\SMS\Drivers\NullDriver;
use App\Services\SMS\Drivers\SmsToDriver;
use App\Services\SMS\Drivers\TwilioDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-aware SMS service.
 *
 * Resolution order:
 *   1. Enterprise tenant with custom SMS configured  → tenant settings
 *   2. Platform SMS config (active provider)         → platform config
 *   3. NullDriver (logs only)
 */
class SmsService
{
    private object $driver;

    public function __construct()
    {
        $this->driver = $this->resolveDriver();
    }

    public function send(string $phone, string $message): bool
    {
        return $this->driver->send($phone, $message);
    }

    public function sendOtp(string $phone, string $otp, int $ttlMinutes = 5): bool
    {
        $message = "Your LENDR verification code is: {$otp}. Valid for {$ttlMinutes} minutes. Do not share this code.";

        return $this->send($phone, $message);
    }

    // ─── Driver resolution ───────────────────────────────────────────────────

    private function resolveDriver(): object
    {
        // 1. Enterprise tenant with custom SMS configured
        $tenantDriver = $this->resolveTenantDriver();
        if ($tenantDriver) {
            return $tenantDriver;
        }

        // 2. Platform SMS config
        $platformDriver = $this->resolvePlatformDriver();
        if ($platformDriver) {
            return $platformDriver;
        }

        // 3. Fallback — log only
        return new NullDriver;
    }

    private function resolveTenantDriver(): ?object
    {
        $tenant = tenancy()->tenant ?? null;

        if (! $tenant || $tenant->plan !== 'enterprise') {
            return null;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            $settings = DB::table('settings')
                ->whereIn('key', ['sms_gateway', 'sms_api_key', 'sms_sender_name', 'sms_username'])
                ->pluck('value', 'key');

            $gateway = $settings->get('sms_gateway');
            $apiKey = $settings->get('sms_api_key', '');

            if (empty($apiKey) || empty($gateway) || $gateway === 'null') {
                return null;
            }

            return $this->buildDriver($gateway, $apiKey, $settings);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolvePlatformDriver(): ?object
    {
        try {
            $config = PlatformSmsConfig::active();

            if (! $config || ! $config->isConfigured()) {
                return null;
            }

            return $this->buildDriver(
                $config->provider,
                $config->api_key,
                collect([
                    'sms_username' => $config->username ?? 'sandbox',
                    'sms_sender_name' => $config->sender_id ?? 'LENDR',
                ]),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildDriver(string $gateway, string $apiKey, $settings): ?object
    {
        return match ($gateway) {
            'africas_talking' => new AfrikasTalkingDriver(
                apiKey: $apiKey,
                username: $settings->get('sms_username', 'sandbox'),
                senderId: $settings->get('sms_sender_name', 'LENDR'),
                sandbox: app()->isLocal(),
            ),
            'sms_to' => new SmsToDriver(
                apiKey: $apiKey,
                senderId: $settings->get('sms_sender_name', 'LENDR'),
            ),
            'twilio' => new TwilioDriver(
                sid: $settings->get('sms_twilio_sid', config('services.twilio.sid', '')),
                authToken: $apiKey,
                fromNumber: $settings->get('sms_sender_name', config('services.twilio.from', '')),
            ),
            'clickatell' => new ClickatellDriver(
                apiKey: $apiKey,
                senderId: $settings->get('sms_sender_name', 'LENDR'),
            ),
            default => null,
        };
    }
}
