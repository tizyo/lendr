<?php

namespace App\Services;

use App\Models\Landlord\CrbIdentity;
use App\Models\Landlord\CrbInquiry;
use App\Models\Landlord\CrbScoreEvent;
use Illuminate\Support\Facades\DB;

/**
 * Central Credit Reference Bureau service.
 *
 * All identifiers are SHA-256 hashed before storage — raw PII never persists here.
 * This service ALWAYS runs in the landlord (central) DB context.
 *
 * Score range: 300 (very poor) – 850 (excellent)
 * Default starting score: 600 (fair)
 */
class CrbService
{
    // ── Score Band Boundaries ───────────────────────────────────────────────
    private const SCORE_MIN = 300;
    private const SCORE_MAX = 850;
    private const SCORE_DEFAULT = 600;

    private const BANDS = [
        750 => 'excellent',
        700 => 'very_good',
        650 => 'good',
        600 => 'fair',
        550 => 'poor',
        0   => 'very_poor',
    ];

    // ── Event Points Table (real-world credit scoring model) ────────────────
    private const EVENT_POINTS = [
        'loan_opened'             => -5,   // hard inquiry effect
        'early_repayment'         => +15,  // paid before due date
        'on_time_repayment'       => +5,   // paid within grace period
        'late_payment_mild'       => -10,  // 1–30 DPD
        'late_payment_moderate'   => -25,  // 31–60 DPD
        'late_payment_severe'     => -40,  // 61–90 DPD
        'default'                 => -80,  // 90+ DPD
        'loan_completed'          => +20,  // fully repaid
        'writeoff'                => -150, // written off
        'rehabilitation'          => +30,  // previously written off, now repaid
        'multiple_loans_penalty'  => -15,  // >1 concurrent loan
        'inquiry'                 => -2,   // soft check (minimal impact)
    ];

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Hash an identifier (NRC / TPIN / company reg) deterministically.
     * The type is included in the hash to prevent cross-type collisions.
     */
    public function hash(string $value, string $type): string
    {
        return hash('sha256', strtolower($type) . ':' . strtolower(trim($value)));
    }

    /**
     * Look up or create a CRB identity record for the given hash.
     */
    public function getOrCreate(string $hash, string $type): CrbIdentity
    {
        return CrbIdentity::firstOrCreate(
            ['identity_hash' => $hash],
            ['identity_type' => $type, 'credit_score' => self::SCORE_DEFAULT]
        );
    }

    /**
     * Check the CRB profile for an identifier.
     * Returns a standardised result that tenants may act on.
     * Records a soft inquiry (minimal score impact).
     *
     * @return array{
     *   found: bool,
     *   credit_score: int,
     *   score_band: string,
     *   risk_level: string,
     *   active_loan_count: int,
     *   has_active_loans: bool,
     *   total_loans_taken: int,
     *   total_loans_completed: int,
     *   total_loans_defaulted: int,
     *   completion_rate: float,
     *   credit_age_months: int|null,
     * }
     */
    public function check(string $hash, string $type, string $tenantId, string $purpose = 'manual_check'): array
    {
        $identity = CrbIdentity::where('identity_hash', $hash)->first();

        if (! $identity) {
            $this->logInquiry($hash, $tenantId, $purpose, null, 0, null, false);
            return [
                'found'                 => false,
                'credit_score'          => self::SCORE_DEFAULT,
                'score_band'            => 'fair',
                'risk_level'            => 'fair',
                'active_loan_count'     => 0,
                'has_active_loans'      => false,
                'total_loans_taken'     => 0,
                'total_loans_completed' => 0,
                'total_loans_defaulted' => 0,
                'completion_rate'       => 0.0,
                'credit_age_months'     => null,
            ];
        }

        $this->logInquiry(
            $hash, $tenantId, $purpose,
            $identity->credit_score,
            $identity->active_loan_count,
            $identity->score_band,
            $identity->active_loan_count > 0,
        );

        return [
            'found'                 => true,
            'credit_score'          => $identity->credit_score,
            'score_band'            => $identity->score_band,
            'risk_level'            => $identity->score_band,
            'active_loan_count'     => $identity->active_loan_count,
            'has_active_loans'      => $identity->active_loan_count > 0,
            'total_loans_taken'     => $identity->total_loans_taken,
            'total_loans_completed' => $identity->total_loans_completed,
            'total_loans_defaulted' => $identity->total_loans_defaulted,
            'completion_rate'       => $identity->total_loans_taken > 0
                ? round($identity->total_loans_completed / $identity->total_loans_taken * 100, 1)
                : 0.0,
            'credit_age_months'     => $identity->first_loan_date
                ? (int) $identity->first_loan_date->diffInMonths(now())
                : null,
        ];
    }

    /**
     * Record that a new loan has been opened for this identity.
     * Fires: loan_opened event; multiple_loans_penalty if >1 active loan.
     */
    public function recordLoanOpened(
        string $hash,
        string $type,
        string $tenantId,
        float  $amount,
        string $loanNumber
    ): void {
        $identity = $this->getOrCreate($hash, $type);

        DB::transaction(function () use ($identity, $amount, $loanNumber, $tenantId) {
            $identity->increment('total_loans_taken');
            $identity->increment('active_loan_count');
            $identity->increment('total_amount_borrowed', $amount);

            if ($identity->first_loan_date === null) {
                $identity->update(['first_loan_date' => now()->toDateString()]);
            }

            // Hard inquiry / new loan penalty
            $this->applyEvent($identity, 'loan_opened', $tenantId, $loanNumber);

            // Penalty for having multiple concurrent loans
            $identity->refresh();
            if ($identity->active_loan_count > 1) {
                $this->applyEvent($identity, 'multiple_loans_penalty', $tenantId, $loanNumber,
                    "Active concurrent loans: {$identity->active_loan_count}");
            }
        });
    }

    /**
     * Record a payment event against the identity.
     * Determines the appropriate event type from DPD.
     *
     * @param int   $dpd          Days past due at time of payment (0 = current/early)
     * @param bool  $isEarly      True if paid before the due date
     * @param bool  $loanCompleted True if this payment fully closes the loan
     * @param bool  $wasWrittenOff True if this is recovery on a written-off loan
     */
    public function recordPayment(
        string $hash,
        string $type,
        string $tenantId,
        string $loanNumber,
        int    $dpd,
        bool   $isEarly,
        bool   $loanCompleted,
        float  $amountPaid,
        bool   $wasWrittenOff = false
    ): void {
        $identity = $this->getOrCreate($hash, $type);

        DB::transaction(function () use (
            $identity, $tenantId, $loanNumber, $dpd, $isEarly, $loanCompleted, $amountPaid, $wasWrittenOff
        ) {
            $identity->increment('total_amount_repaid', $amountPaid);

            // Determine payment quality event
            $paymentEvent = match (true) {
                $wasWrittenOff          => 'rehabilitation',
                $isEarly || $dpd === 0  => 'early_repayment',
                $dpd <= 30              => 'on_time_repayment',  // within grace/mild
                $dpd <= 60              => 'late_payment_mild',
                $dpd <= 90              => 'late_payment_moderate',
                $dpd <= 180             => 'late_payment_severe',
                default                 => 'default',
            };

            $this->applyEvent($identity, $paymentEvent, $tenantId, $loanNumber, "DPD: {$dpd}", $dpd);

            if ($loanCompleted) {
                $identity->decrement('active_loan_count');
                $identity->increment('total_loans_completed');
                $this->applyEvent($identity, 'loan_completed', $tenantId, $loanNumber);
            }
        });
    }

    /**
     * Record a loan write-off.
     */
    public function recordWriteoff(
        string $hash,
        string $type,
        string $tenantId,
        string $loanNumber
    ): void {
        $identity = $this->getOrCreate($hash, $type);

        DB::transaction(function () use ($identity, $tenantId, $loanNumber) {
            $identity->decrement('active_loan_count');
            $identity->increment('total_loans_written_off');
            $identity->increment('total_loans_defaulted');

            $this->applyEvent($identity, 'writeoff', $tenantId, $loanNumber);
        });
    }

    /**
     * Recalculate the score from scratch based on all events.
     * Used for periodic reconciliation.
     */
    public function recalculateFromEvents(CrbIdentity $identity): void
    {
        $events = CrbScoreEvent::where('identity_hash', $identity->identity_hash)
            ->orderBy('created_at')
            ->pluck('points_change')
            ->sum();

        $score = $this->clamp(self::SCORE_DEFAULT + $events);

        $identity->update([
            'credit_score'          => $score,
            'score_band'            => $this->scoreBand($score),
            'last_score_updated_at' => now(),
        ]);
    }

    /**
     * Get full CRB report for an identity (landlord admin use).
     */
    public function fullReport(string $hash): ?array
    {
        $identity = CrbIdentity::where('identity_hash', $hash)->first();
        if (! $identity) {
            return null;
        }

        $recentEvents = CrbScoreEvent::where('identity_hash', $hash)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($e) => [
                'event_type'    => $e->event_type,
                'points_change' => $e->points_change,
                'score_after'   => $e->score_after,
                'dpd'           => $e->dpd,
                'description'   => $e->description,
                'created_at'    => $e->created_at->toDateString(),
            ]);

        $inquiryCount30d = CrbInquiry::where('identity_hash', $hash)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'identity_type'         => $identity->identity_type,
            'credit_score'          => $identity->credit_score,
            'score_band'            => $identity->score_band,
            'active_loan_count'     => $identity->active_loan_count,
            'total_loans_taken'     => $identity->total_loans_taken,
            'total_loans_completed' => $identity->total_loans_completed,
            'total_loans_defaulted' => $identity->total_loans_defaulted,
            'total_loans_written_off' => $identity->total_loans_written_off,
            'total_amount_borrowed' => (float) $identity->total_amount_borrowed,
            'total_amount_repaid'   => (float) $identity->total_amount_repaid,
            'completion_rate'       => $identity->total_loans_taken > 0
                ? round($identity->total_loans_completed / $identity->total_loans_taken * 100, 1)
                : 0.0,
            'credit_age_months'     => $identity->first_loan_date
                ? (int) $identity->first_loan_date->diffInMonths(now())
                : null,
            'inquiries_last_30_days' => $inquiryCount30d,
            'last_score_updated_at' => $identity->last_score_updated_at?->toIso8601String(),
            'recent_events'         => $recentEvents,
        ];
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function applyEvent(
        CrbIdentity $identity,
        string      $eventType,
        string      $tenantId,
        string      $loanNumber,
        ?string     $description = null,
        ?int        $dpd = null
    ): void {
        $identity->refresh();

        $points     = self::EVENT_POINTS[$eventType] ?? 0;
        $scoreBefore = $identity->credit_score;
        $scoreAfter  = $this->clamp($scoreBefore + $points);

        CrbScoreEvent::create([
            'identity_hash'       => $identity->identity_hash,
            'event_type'          => $eventType,
            'points_change'       => $points,
            'score_before'        => $scoreBefore,
            'score_after'         => $scoreAfter,
            'dpd'                 => $dpd,
            'tenant_id'           => $tenantId,
            'loan_reference_hash' => hash('sha256', $loanNumber),
            'description'         => $description,
            'created_at'          => now(),
        ]);

        $identity->update([
            'credit_score'          => $scoreAfter,
            'score_band'            => $this->scoreBand($scoreAfter),
            'last_score_updated_at' => now(),
        ]);
    }

    private function logInquiry(
        string  $hash,
        string  $tenantId,
        string  $purpose,
        ?int    $score,
        int     $activeLoans,
        ?string $riskLevel,
        bool    $hasActiveLoans
    ): void {
        CrbInquiry::create([
            'identity_hash'        => $hash,
            'tenant_id'            => $tenantId,
            'purpose'              => $purpose,
            'result_score'         => $score,
            'result_active_loans'  => $activeLoans,
            'result_risk_level'    => $riskLevel,
            'result_has_active_loans' => $hasActiveLoans,
            'override_requested'   => false,
            'created_at'           => now(),
        ]);
    }

    private function clamp(int $score): int
    {
        return max(self::SCORE_MIN, min(self::SCORE_MAX, $score));
    }

    private function scoreBand(int $score): string
    {
        foreach (self::BANDS as $threshold => $band) {
            if ($score >= $threshold) {
                return $band;
            }
        }
        return 'very_poor';
    }
}
