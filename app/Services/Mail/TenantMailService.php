<?php

namespace App\Services\Mail;

use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-aware mail service.
 *
 * Resolution order:
 *   1. Enterprise tenant with custom SMTP configured  → tenant SMTP settings
 *   2. "platform" mailer                              → landlord Platform Settings
 *                                                       (Resend / SMTP), else the
 *                                                       .env fallback mailer
 *
 * The From name (tenant slug vs LENDR) is applied centrally by
 * App\Mail\Listeners\ApplyPlatformSender.
 */
class TenantMailService
{
    /**
     * Send a mailable to the given address using the correct mailer for the current tenant.
     */
    public function send(string|array $to, Mailable $mailable): void
    {
        $this->resolveMailer()->to($to)->send($mailable);
    }

    /**
     * Send a raw text message (for test emails etc.).
     */
    public function raw(string $to, string $subject, string $body): void
    {
        $this->resolveMailer()->raw($body, fn ($m) => $m->to($to)->subject($subject));
    }

    // ─── Mailer resolution ────────────────────────────────────────────────────

    private function resolveMailer(): MailerContract
    {
        $tenantConfig = $this->tenantSmtpConfig();

        if ($tenantConfig) {
            $mailer = Mail::build($tenantConfig);

            if (! empty($tenantConfig['from_address'])) {
                $mailer->alwaysFrom($tenantConfig['from_address'], $tenantConfig['from_name'] ?: null);
            }

            return $mailer;
        }

        return Mail::mailer('platform');
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
                'from_address' => $s->get('smtp_from_email'),
                'from_name' => $s->get('smtp_from_name'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
