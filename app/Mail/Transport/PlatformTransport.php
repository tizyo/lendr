<?php

namespace App\Mail\Transport;

use App\Models\Landlord\PlatformEmailConfig;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * The "platform" mailer: delivers through whatever the landlord configured in
 * Platform Settings → Email (Resend API or SMTP), falling back to the .env
 * mailer when nothing is configured.
 *
 * The config is read on every send rather than when the mailer is built, so a
 * key saved in the UI takes effect immediately — including in long-running
 * queue workers that resolved the mailer hours ago.
 */
class PlatformTransport implements TransportInterface
{
    public function __construct(
        private MailManager $manager,
        private string $fallbackMailer,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        return $this->resolve()->send($message, $envelope);
    }

    private function resolve(): TransportInterface
    {
        $config = rescue(fn () => PlatformEmailConfig::active(), null, false);

        if ($config && $config->isConfigured()) {
            return $this->manager->createSymfonyTransport($config->toMailerConfig());
        }

        $fallback = config("mail.mailers.{$this->fallbackMailer}");

        if (! is_array($fallback) || ($fallback['transport'] ?? null) === 'platform') {
            $fallback = config('mail.mailers.log');
        }

        return $this->manager->createSymfonyTransport($fallback);
    }

    public function __toString(): string
    {
        return 'platform';
    }
}
