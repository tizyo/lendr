<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Delivers mail through the Resend HTTP API (https://resend.com) instead of SMTP.
 *
 * DigitalOcean blocks outbound SMTP ports, so this is how production sends mail.
 * Registered as the "resend" transport via Mail::extend (overriding Laravel's
 * built-in one, which needs the resend/resend-php package).
 */
class ResendTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Resend API key is not configured.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = array_filter([
            'from' => $this->formatAddress($email->getFrom()[0] ?? null),
            'to' => $this->formatAddresses($email->getTo()),
            'cc' => $this->formatAddresses($email->getCc()),
            'bcc' => $this->formatAddresses($email->getBcc()),
            'reply_to' => $this->formatAddresses($email->getReplyTo()),
            'subject' => $email->getSubject() ?? '',
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'attachments' => $this->attachments($email),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post('https://api.resend.com/emails', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Resend delivery failed: '.($response->json('message') ?? $response->body()));
        }
    }

    /** @param  Address[]  $addresses */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_filter(array_map(fn (Address $a) => $this->formatAddress($a), $addresses)));
    }

    private function formatAddress(?Address $a): ?string
    {
        if (! $a) {
            return null;
        }

        return $a->getName() !== ''
            ? sprintf('"%s" <%s>', str_replace('"', '', $a->getName()), $a->getAddress())
            : $a->getAddress();
    }

    private function attachments(Email $email): array
    {
        $out = [];
        foreach ($email->getAttachments() as $part) {
            $out[] = [
                'filename' => $part->getFilename() ?: 'attachment',
                'content' => base64_encode($part->getBody()),
            ];
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'resend';
    }
}
