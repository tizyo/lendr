<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $body;

    /**
     * $subject is Mailable's own (non-readonly) property, and $message is
     * overwritten in mail views by Laravel's Message object — so neither can
     * be a promoted readonly property. The body is exposed to the view as $body.
     */
    public function __construct(
        public readonly string $borrowerName,
        string $subject,
        string $message,
    ) {
        $this->subject = $subject;
        $this->body = $message;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        // Mailable's own properties (like $subject) aren't auto-shared with the view.
        return new Content(view: 'emails.broadcast', with: ['subject' => $this->subject]);
    }
}
