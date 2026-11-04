<?php

namespace App\Mail;

use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A paid plan is about to end (stage "reminder", with days left) or has ended and
 * the workspace moved back to Starter (stage "ended").
 */
class SubscriptionRenewalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Subscription $subscription,
        public readonly string $stage,
        public readonly int $daysRemaining = 0,
    ) {}

    public function envelope(): Envelope
    {
        $plan = ucfirst($this->subscription->plan);

        return new Envelope(
            subject: $this->stage === 'ended'
                ? "Your LENDR {$plan} plan has ended"
                : "Your LENDR {$plan} plan renews in {$this->daysRemaining} day".($this->daysRemaining === 1 ? '' : 's'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-renewal');
    }
}
