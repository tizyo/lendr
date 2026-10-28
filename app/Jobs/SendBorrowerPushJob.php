<?php

namespace App\Jobs;

use App\Models\Tenant\BorrowerNotification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Mirrors a borrower's in-app notification to their phone as a push
 * notification. Dispatched whenever a BorrowerNotification is created.
 */
class SendBorrowerPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly BorrowerNotification $notification) {}

    public function handle(PushNotificationService $push): void
    {
        $notification = $this->notification->loadMissing('borrower');

        if (! $notification->borrower) {
            return;
        }

        $loanId = $notification->data['loan_id'] ?? null;

        $push->sendToBorrower($notification->borrower, $notification->title, (string) $notification->body, [
            'url' => $loanId ? "/app/loans/{$loanId}" : '/app/notifications',
            'tag' => "{$notification->type}-".($loanId ?? $notification->id),
        ]);
    }
}
