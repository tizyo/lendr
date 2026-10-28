<?php

namespace App\Mail;

use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowFundBalanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $staff,
        public readonly float $balance,
        public readonly float $threshold,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Disbursement fund is running low — ZMW '.number_format($this->balance, 2).' left');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-fund-balance', with: ['fundsUrl' => $this->fundsUrl()]);
    }

    private function fundsUrl(): string
    {
        $tenant = tenancy()->tenant;
        $base = $tenant && ! $tenant->usesSharedPortal() ? $tenant->domainUrl() : null;

        return $base ? $base.'/funds' : $tenant?->loginUrl() ?? url('/funds');
    }
}
