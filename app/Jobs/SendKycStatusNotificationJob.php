<?php

namespace App\Jobs;

use App\Enums\KycStatus;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\KycDocument;
use App\Services\SMS\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a borrower the outcome for one of their KYC documents (scope 8.2:
 * KYC approved/rejected → SMS + push). The in-app notification is mirrored
 * to their phone by SendBorrowerPushJob; a rejection carries the reason so
 * they know what to re-upload.
 */
class SendKycStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const NOTIFIABLE = [KycStatus::Verified, KycStatus::Rejected, KycStatus::Expired];

    public int $tries = 3;

    public function __construct(public readonly KycDocument $document) {}

    public function handle(SmsService $sms): void
    {
        $document = $this->document->loadMissing('borrower:id,first_name,phone');
        $borrower = $document->borrower;

        if (! $borrower) {
            return;
        }

        $type = $document->typeLabel();
        $reason = trim((string) $document->rejection_reason);

        [$kind, $title, $body] = match ($document->status) {
            KycStatus::Verified => ['kyc_verified', 'Document approved', "Your {$type} has been verified. Thank you!"],
            KycStatus::Rejected => ['kyc_rejected', 'Document needs attention', "Your {$type} couldn't be accepted".($reason ? ": {$reason}" : '.').' Please upload it again in the app.'],
            KycStatus::Expired => ['kyc_expired', 'Document expired', "Your {$type} has expired. Please upload a current one to keep your account verified."],
            default => [null, null, null],
        };

        if (! $kind) {
            return;
        }

        BorrowerNotification::create([
            'borrower_id' => $borrower->id,
            'type' => $kind,
            'title' => $title,
            'body' => $body,
            'data' => ['kyc_document_id' => $document->id, 'url' => '/app/kyc/status'],
        ]);

        if ($borrower->phone) {
            rescue(fn () => $sms->send($borrower->phone, "Hi {$borrower->first_name}, {$this->lcfirst($body)}"));
        }
    }

    private function lcfirst(string $text): string
    {
        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
