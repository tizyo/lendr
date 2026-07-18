<?php

namespace App\Jobs;

use App\Mail\BroadcastMail;
use App\Models\Tenant\Borrower;
use App\Services\Mail\TenantMailService;
use App\Services\SMS\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $borrowerId,
        public readonly array $channels,   // ['sms', 'email']
        public readonly string $subject,
        public readonly string $message,
    ) {}

    public function handle(SmsService $sms, TenantMailService $mailer): void
    {
        $borrower = Borrower::find($this->borrowerId);

        if (! $borrower) {
            return;
        }

        if (in_array('sms', $this->channels) && $borrower->phone) {
            $sms->send($borrower->phone, $this->message);
        }

        if (in_array('email', $this->channels) && $borrower->email) {
            $mailer->send($borrower->email, new BroadcastMail(
                borrowerName: trim(($borrower->first_name ?? '').' '.($borrower->last_name ?? '')) ?: 'Valued Customer',
                subject: $this->subject,
                message: $this->message,
            ));
        }
    }
}
