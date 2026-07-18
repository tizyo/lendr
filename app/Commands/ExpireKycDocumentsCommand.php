<?php

namespace App\Commands;

use App\Enums\KycStatus;
use App\Models\Tenant\KycDocument;
use Illuminate\Console\Command;

class ExpireKycDocumentsCommand extends Command
{
    protected $signature = 'lendr:expire-kyc-documents';

    protected $description = 'Mark KYC documents as expired when their expiry date has passed.';

    public function handle(): int
    {
        $expired = KycDocument::where('status', KycStatus::Verified)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($expired as $doc) {
            $doc->update(['status' => KycStatus::Expired]);
            // If borrower had kyc_verified = true, re-check
            $borrower = $doc->borrower;
            $stillVerified = $borrower->kycDocuments()
                ->where('status', KycStatus::Verified->value)
                ->exists();
            if (! $stillVerified && $borrower->kyc_verified) {
                $borrower->update(['kyc_verified' => false]);
            }
            $count++;
        }

        $this->info("Expired {$count} KYC document(s).");

        return self::SUCCESS;
    }
}
