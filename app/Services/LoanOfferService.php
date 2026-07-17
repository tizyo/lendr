<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoanOffer;
use App\Models\Tenant\LoanOfferRule;

class LoanOfferService
{
    /**
     * Generate loan offer(s) for a borrower based on their credit score and active rules.
     * Skips if a non-expired pending offer already exists for the same rule.
     *
     * @return LoanOffer[]
     */
    public function generateForBorrower(Borrower $borrower): array
    {
        $score = (int) ($borrower->credit_score ?? 0);

        if ($score <= 0) {
            return [];
        }

        $rules   = LoanOfferRule::forScore($score);
        $created = [];

        foreach ($rules as $rule) {
            // Skip if a live pending offer already exists for this rule + borrower
            $exists = LoanOffer::where('loan_offer_rule_id', $rule->id)
                ->where('borrower_id', $borrower->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->exists();

            if ($exists) {
                continue;
            }

            // Offer amount: scale within rule range based on credit score position
            $scoreRange = max(1, $rule->max_credit_score - $rule->min_credit_score);
            $position   = ($score - $rule->min_credit_score) / $scoreRange;
            $amountRange = $rule->max_offered_amount - $rule->min_offered_amount;
            $offeredAmount = round($rule->min_offered_amount + ($position * $amountRange), 2);

            $plan = $rule->loanPlan;

            $offer = LoanOffer::create([
                'loan_offer_rule_id'   => $rule->id,
                'borrower_id'          => $borrower->id,
                'loan_plan_id'         => $rule->loan_plan_id,
                'offered_amount'       => $offeredAmount,
                'interest_rate'        => $plan->interest_rate,
                'tenure'               => $plan->min_tenure ?? 12,
                'credit_score_at_offer' => $score,
                'status'               => 'pending',
                'expires_at'           => now()->addDays($rule->validity_days),
            ]);

            $created[] = $offer;
        }

        return $created;
    }

    /**
     * Accept an offer — creates a draft loan application.
     */
    public function accept(LoanOffer $offer, int $userId): LoanOffer
    {
        if (! $offer->isPending()) {
            throw new \RuntimeException('Offer is no longer available.');
        }

        $offer->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        return $offer;
    }

    /**
     * Decline an offer.
     */
    public function decline(LoanOffer $offer, ?string $reason = null): LoanOffer
    {
        if (! $offer->isPending()) {
            throw new \RuntimeException('Offer is no longer available.');
        }

        $offer->update([
            'status'         => 'declined',
            'declined_at'    => now(),
            'decline_reason' => $reason,
        ]);

        return $offer;
    }

    /**
     * Expire all pending offers whose expires_at has passed.
     * Called by scheduled command.
     */
    public function expireStale(): int
    {
        return LoanOffer::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Generate offers for all eligible borrowers in a batch.
     */
    public function generateBatch(): int
    {
        $count = 0;

        Borrower::where('is_active', true)
            ->whereNotNull('credit_score')
            ->where('credit_score', '>', 0)
            ->chunk(100, function ($borrowers) use (&$count) {
                foreach ($borrowers as $borrower) {
                    $count += count($this->generateForBorrower($borrower));
                }
            });

        return $count;
    }
}
