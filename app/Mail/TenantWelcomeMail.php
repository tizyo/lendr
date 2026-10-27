<?php

namespace App\Mail;

use App\Models\Landlord\Tenant;
use App\Services\Mail\EmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to a new workspace's admin once they verify their email (platform-branded). */
class TenantWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.app(EmailBrand::class)->current()['name']." — {$this->tenant->name} is ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-welcome',
        );
    }
}
