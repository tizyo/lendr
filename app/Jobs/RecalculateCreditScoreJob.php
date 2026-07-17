<?php

namespace App\Jobs;

use App\Models\Tenant\Borrower;
use App\Services\CreditScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateCreditScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10;

    public function __construct(public readonly int $borrowerId) {}

    public function handle(CreditScoringService $scorer): void
    {
        $borrower = Borrower::find($this->borrowerId);

        if (! $borrower) {
            return;
        }

        $score = $scorer->calculate($borrower);

        $tier = Borrower::tierFromScore($score);

        $borrower->forceFill([
            'credit_score'            => $score,
            'credit_score_updated_at' => now(),
            'verification_tier'       => $tier,
        ])->save();

        Log::info('[CreditScore] Recalculated', [
            'borrower_id'       => $borrower->id,
            'score'             => $score,
            'verification_tier' => $tier,
        ]);
    }
}
