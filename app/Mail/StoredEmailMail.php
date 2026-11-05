<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

/**
 * Sends a logged email again exactly as it first went out (see EmailLog).
 */
class StoredEmailMail extends Mailable
{
    /**
     * @param  array{tenant_id?: string, sent_by?: string, resent_from_id?: int}  $logMeta  read by RecordOutgoingEmail
     */
    public function __construct(
        string $subject,
        ?string $html,
        private readonly ?string $plainText,
        public array $logMeta = [],
    ) {
        $this->subject($subject);

        // A Mailable needs a body to render; a text-only email gets a plain HTML copy.
        $this->html($html ?: nl2br(e((string) $plainText)));
    }

    public function build(): static
    {
        if ($this->plainText) {
            $text = $this->plainText;
            $this->withSymfonyMessage(fn (Email $message) => $message->text($text));
        }

        return $this;
    }
}
