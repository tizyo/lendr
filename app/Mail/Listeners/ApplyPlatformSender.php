<?php

namespace App\Mail\Listeners;

use App\Models\Landlord\PlatformEmailConfig;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Stamps the platform sender onto every outgoing message that uses the
 * default From (i.e. the mailable didn't set its own):
 *
 *   - address → the landlord-configured From Address (must be on a domain
 *               verified in Resend), else MAIL_FROM_ADDRESS
 *   - name    → the tenant's workspace slug when sent in tenant context,
 *               otherwise the platform name (LENDR) for system emails
 *
 * Messages with an explicit From (e.g. an enterprise tenant's own SMTP
 * sender) are left untouched.
 *
 * Registered in AppServiceProvider — deliberately outside app/Listeners so
 * event discovery can't register it a second time.
 */
class ApplyPlatformSender
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;
        $current = $message->getFrom()[0] ?? null;
        $defaultAddress = config('mail.from.address');

        if ($current && strcasecmp($current->getAddress(), (string) $defaultAddress) !== 0) {
            return;
        }

        $platform = rescue(fn () => PlatformEmailConfig::active(), null, false);

        $address = $platform?->from_address ?: $defaultAddress;

        if (! $address) {
            return;
        }

        $message->from(new Address($address, $this->senderName($platform)));
    }

    private function senderName(?PlatformEmailConfig $platform): string
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        if ($tenant?->slug) {
            return $tenant->slug;
        }

        return $platform?->from_name ?: 'LENDR';
    }
}
