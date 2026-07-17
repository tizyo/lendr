<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoyaltyAccount;
use App\Models\Tenant\LoyaltyPoint;
use App\Models\Tenant\LoyaltyTier;
use App\Models\Tenant\Payment;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Points earned per currency unit repaid (1 point per 10 units).
     */
    private const POINTS_PER_UNIT = 10;

    /**
     * Award points for a repayment. Called after a payment is recorded.
     */
    public function awardForPayment(Payment $payment): LoyaltyAccount
    {
        $points = (int) floor($payment->amount / self::POINTS_PER_UNIT);

        if ($points <= 0) {
            return $this->getOrCreate($payment->loan->borrower_id);
        }

        return $this->addPoints(
            $payment->loan->borrower_id,
            $points,
            'earned',
            'Repayment on loan #' . $payment->loan->loan_number,
            $payment->id
        );
    }

    /**
     * Redeem points from a borrower's account.
     */
    public function redeem(Borrower $borrower, int $points, string $description = ''): LoyaltyAccount
    {
        $account = $this->getOrCreate($borrower->id);

        if ($points > $account->total_points) {
            throw new \InvalidArgumentException('Insufficient loyalty points.');
        }

        return $this->addPoints($borrower->id, -$points, 'redeemed', $description ?: 'Points redeemed');
    }

    /**
     * Get the processing fee discount % for a borrower based on their loyalty tier.
     */
    public function feeDiscount(Borrower $borrower): float
    {
        $account = LoyaltyAccount::where('borrower_id', $borrower->id)->first();
        if (! $account) {
            return 0.0;
        }
        return LoyaltyTier::discountFor($account->tier);
    }

    /**
     * Get or create a loyalty account for a borrower.
     */
    public function getOrCreate(int $borrowerId): LoyaltyAccount
    {
        return LoyaltyAccount::firstOrCreate(
            ['borrower_id' => $borrowerId],
            ['total_points' => 0, 'tier' => 'Bronze']
        );
    }

    /**
     * Get the full points ledger for a borrower.
     */
    public function ledger(Borrower $borrower): array
    {
        $account = $this->getOrCreate($borrower->id);

        $points = LoyaltyPoint::where('borrower_id', $borrower->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'points'      => $p->points,
                'type'        => $p->type,
                'description' => $p->description,
                'payment_id'  => $p->payment_id,
                'created_at'  => $p->created_at?->toDateTimeString(),
            ]);

        return [
            'account' => [
                'total_points' => $account->total_points,
                'tier'         => $account->tier,
                'fee_discount' => LoyaltyTier::discountFor($account->tier),
            ],
            'transactions' => $points,
        ];
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function addPoints(
        int $borrowerId,
        int $points,
        string $type,
        string $description = '',
        ?int $paymentId = null
    ): LoyaltyAccount {
        return DB::transaction(function () use ($borrowerId, $points, $type, $description, $paymentId) {
            LoyaltyPoint::create([
                'borrower_id' => $borrowerId,
                'points'      => $points,
                'type'        => $type,
                'description' => $description,
                'payment_id'  => $paymentId,
            ]);

            $account = $this->getOrCreate($borrowerId);
            $newTotal = max(0, $account->total_points + $points);
            $newTier  = LoyaltyTier::resolveFor($newTotal);

            $account->update([
                'total_points' => $newTotal,
                'tier'         => $newTier,
            ]);

            return $account->fresh();
        });
    }
}
