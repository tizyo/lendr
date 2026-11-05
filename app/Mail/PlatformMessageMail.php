<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A message written by LENDR support to a workspace's owner or staff, sent from
 * the landlord's tenant page. Platform-branded, like the onboarding emails.
 */
class PlatformMessageMail extends Mailable
{
    public readonly string $body;

    /**
     * $subject is Mailable's own property and $message is taken by Laravel in mail
     * views, so the text reaches the view as $body (see BroadcastMail).
     *
     * @param  array{tenant_id?: string, sent_by?: string}  $logMeta  read by RecordOutgoingEmail
     */
    public function __construct(
        string $subject,
        string $body,
        public readonly ?string $recipientName = null,
        public readonly ?string $buttonLabel = null,
        public readonly ?string $buttonUrl = null,
        public array $logMeta = [],
    ) {
        $this->subject = $subject;
        $this->body = $body;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform-message', with: ['subject' => $this->subject]);
    }
}
