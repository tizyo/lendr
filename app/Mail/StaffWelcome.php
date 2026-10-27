<?php

namespace App\Mail;

use App\Models\Tenant\User;
use App\Services\Mail\EmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $staff,
        public readonly string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.app(EmailBrand::class)->current()['name'].' — your account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-welcome',
            with: ['loginUrl' => tenancy()->tenant?->loginUrl() ?? url('/login')],
        );
    }
}
