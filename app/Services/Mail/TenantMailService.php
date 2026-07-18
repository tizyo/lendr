<?php

namespace App\Services\Mail;

use App\Models\Landlord\PlatformEmailConfig;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-aware mail service.
 *
 * Resolution order:
 *   1. Enterprise tenant with custom SMTP configured  → tenant SMTP settings
 *   2. Platform email config (active SMTP)            → platform config
 *   3. Laravel default mailer                         → .env config
 */
class TenantMailService
{
    /**
     * Send a mailable to the given address using the correct mailer for the current tenant.
     */
    public function send(string|array $to, Mailable $mailable): void
    {
        $mailer = $this->resolveMailer();

        if ($mailer instanceof \Illuminate\Mail\Mailer) {
            $mailer->to($to)->send($mailable);
        } else {
            Mail::to($to)->send($mailable);
        }
    }

    /**
     * Send a raw text message (for test emails etc.).
     */
    public function raw(string $to, string $subject, string $body): void
    {
        $from = $this->resolveFromAddress();

        $mailer = $this->resolveMailer();

        $send = fn ($m) => $m->to($to)->subject($subject);

        if ($mailer instanceof \Illuminate\Mail\Mailer) {
            $mailer->raw($body, $send);
        } else {
            Mail::raw($body, $send);
        }
    }

    // ─── Mailer resolution ───────────────────────────────────────────────────

    private function resolveMailer(): mixed
    {
        // 1. Enterprise tenant with custom SMTP
        $tenantConfig = $this->tenantSmtpConfig();
        if ($tenantConfig) {
            return Mail::build($tenantConfig);
        }

        // 2. Platform email config
        $platformConfig = $this->platformSmtpConfig();
        if ($platformConfig) {
            return Mail::build($platformConfig);
        }

        // 3. Default .env mailer
        return null; // caller uses Mail facade
    }

    private function tenantSmtpConfig(): ?array
    {
        $tenant = tenancy()->tenant ?? null;

        if (! $tenant || $tenant->plan !== 'enterprise') {
            return null;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            $s = DB::table('settings')
                ->whereIn('key', ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name', 'smtp_encryption'])
                ->pluck('value', 'key');

            $host = $s->get('smtp_host');
            $user = $s->get('smtp_username');

            if (empty($host) || empty($user)) {
                return null;
            }

            return [
                'transport' => 'smtp',
                'host' => $host,
                'port' => (int) ($s->get('smtp_port', 587)),
                'encryption' => $s->get('smtp_encryption', 'tls') ?: null,
                'username' => $user,
                'password' => $s->get('smtp_password', ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function platformSmtpConfig(): ?array
    {
        try {
            $config = PlatformEmailConfig::active();

            if (! $config || ! $config->isConfigured()) {
                return null;
            }

            return $config->toMailerConfig();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveFromAddress(): array
    {
        // Try tenant SMTP from/name first, then platform, then .env
        $tenant = tenancy()->tenant ?? null;

        if ($tenant && $tenant->plan === 'enterprise') {
            try {
                $s = DB::table('settings')
                    ->whereIn('key', ['smtp_from_email', 'smtp_from_name'])
                    ->pluck('value', 'key');
                if ($s->get('smtp_from_email')) {
                    return [$s->get('smtp_from_email') => $s->get('smtp_from_name', 'LENDR')];
                }
            } catch (\Throwable) {
            }
        }

        $platform = PlatformEmailConfig::active();
        if ($platform?->from_address) {
            return [$platform->from_address => ($platform->from_name ?? 'LENDR')];
        }

        return [config('mail.from.address', 'noreply@lendr.app') => config('mail.from.name', 'LENDR')];
    }
}
