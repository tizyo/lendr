<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Credit scoring engine — produces a score in the range 300–850.
 *
 * Components (weights mirror simplified FICO logic):
 *   35% Payment history  — ratio of on-time payments (is_overdue_payment = false)
 *   30% Utilization      — outstanding balance vs total credit extended
 *   15% Credit age       — months since first loan was disbursed
 *   10% Completed loans  — number of fully repaid loans (capped at 5)
 *   10% Default penalty  — deduct points for defaulted loans and blacklisting
 */
class CreditScoringService
{
    private const BASE  = 300;
    private const RANGE = 550; // 300 + 550 = 850 max

    public function calculate(Borrower $borrower): int
    {
        $loans = Loan::where('borrower_id', $borrower->id)
            ->whereIn('status', ['disbursed', 'active', 'completed', 'defaulted', 'written_off'])
            ->get();

        if ($loans->isEmpty()) {
            // No credit history — baseline score
            return 450;
        }

        $loanIds = $loans->pluck('id');

        $payments = Payment::whereIn('loan_id', $loanIds)->get();

        $score = self::BASE
            + $this->paymentHistory($payments)
            + $this->utilizationScore($loans)
            + $this->creditAgeScore($loans)
            + $this->completedLoanScore($loans)
            + $this->defaultPenalty($loans, $borrower);

        return (int) max(300, min(850, round($score)));
    }

    // ─── Components ──────────────────────────────────────────────────────────

    /** 35% weight → max 192.5 pts */
    private function paymentHistory(\Illuminate\Support\Collection $payments): float
    {
        $total = $payments->count();
        if ($total === 0) {
            return 0;
        }

        $onTime = $payments->where('is_overdue_payment', false)->count();
        $ratio  = $onTime / $total;

        return round($ratio * self::RANGE * 0.35, 2);
    }

    /** 30% weight → max 165 pts (lower utilization = higher score) */
    private function utilizationScore(\Illuminate\Support\Collection $loans): float
    {
        $totalExtended    = $loans->sum('principal_amount');
        $totalOutstanding = $loans->whereIn('status', ['disbursed', 'active'])->sum('outstanding_balance');

        if ($totalExtended <= 0) {
            return self::RANGE * 0.30;
        }

        $utilization = min(1.0, $totalOutstanding / $totalExtended);
        $inverseUtil = 1 - $utilization;

        return round($inverseUtil * self::RANGE * 0.30, 2);
    }

    /** 15% weight → max 82.5 pts — full credit at 24+ months */
    private function creditAgeScore(\Illuminate\Support\Collection $loans): float
    {
        $firstDisbursed = $loans
            ->whereNotNull('disbursement_date')
            ->sortBy('disbursement_date')
            ->first()?->disbursement_date;

        if (! $firstDisbursed) {
            return 0;
        }

        $months = (int) now()->diffInMonths($firstDisbursed);
        $capped = min($months, 24);

        return round(($capped / 24) * self::RANGE * 0.15, 2);
    }

    /** 10% weight → max 55 pts — full credit at 5+ completed loans */
    private function completedLoanScore(\Illuminate\Support\Collection $loans): float
    {
        $completed = $loans->whereIn('status', ['completed'])->count();
        $capped    = min($completed, 5);

        return round(($capped / 5) * self::RANGE * 0.10, 2);
    }

    /** 10% weight — deduct for defaults, write-offs, and blacklisting */
    private function defaultPenalty(\Illuminate\Support\Collection $loans, Borrower $borrower): float
    {
        $maxPoints = self::RANGE * 0.10;

        $defaults = $loans->whereIn('status', ['defaulted', 'written_off'])->count();
        $total    = $loans->count();

        $deductRatio = $total > 0 ? min(1.0, $defaults / $total) : 0;

        $points = $maxPoints * (1 - $deductRatio);

        if ($borrower->is_blacklisted) {
            $points = 0;
        }

        return round($points, 2);
    }

    // ─── Cross-tenant global recalculation ───────────────────────────────────

    /**
     * Recalculate and persist credit score for a cross-tenant borrower identity.
     *
     * $borrowerGlobalId = SHA256(phone_number) — stored in central credit_scores table.
     * Looks up borrower in current tenant context, calculates score, then persists
     * the factor breakdown to the central DB.
     */
    public function recalculate(string $borrowerGlobalId): ?object
    {
        // Resolve borrower in current tenant context by global_id (SHA256 phone)
        $borrower = Borrower::whereRaw('SHA2(phone, 256) = ?', [$borrowerGlobalId])->first();

        if (! $borrower) {
            return null;
        }

        $loans = Loan::where('borrower_id', $borrower->id)
            ->whereIn('status', ['disbursed', 'active', 'completed', 'defaulted', 'written_off'])
            ->get();

        $loanIds  = $loans->pluck('id');
        $payments = $loanIds->isNotEmpty() ? Payment::whereIn('loan_id', $loanIds)->get() : collect();

        // Factor subscores (0–100)
        $repaymentScore  = $this->computeRepaymentHistoryFactor($payments);
        $debtScore       = $this->computeDebtLoadFactor($loans, $borrower);
        $historyScore    = $this->computeHistoryLengthFactor($loans);
        $mixScore        = $this->computeAccountMixFactor($loans);
        $newCreditScore  = $this->computeNewCreditFactor($loans);

        // Weighted composite raw score (0–100)
        $raw = ($repaymentScore * 0.40)
             + ($debtScore      * 0.25)
             + ($historyScore   * 0.15)
             + ($mixScore       * 0.10)
             + ($newCreditScore * 0.10);

        // Convert to 300–850 range
        $score = (int) max(300, min(850, round($raw * 5.5 + 300)));
        $band  = $this->scoreBand($score);

        $record = DB::connection('mysql')->table('credit_scores')->updateOrInsert(
            ['borrower_global_id' => $borrowerGlobalId],
            [
                'score'                  => $score,
                'score_band'             => $band,
                'repayment_history_score'=> $repaymentScore,
                'debt_load_score'        => $debtScore,
                'history_length_score'   => $historyScore,
                'account_mix_score'      => $mixScore,
                'new_credit_score'       => $newCreditScore,
                'total_loans'            => $loans->count(),
                'total_completed'        => $loans->where('status', 'completed')->count(),
                'total_defaulted'        => $loans->whereIn('status', ['defaulted', 'written_off'])->count(),
                'last_updated'           => now(),
                'updated_at'             => now(),
            ]
        );

        return DB::connection('mysql')->table('credit_scores')
            ->where('borrower_global_id', $borrowerGlobalId)
            ->first();
    }

    public function scoreBand(int $score): string
    {
        return match(true) {
            $score >= 750 => 'excellent',
            $score >= 650 => 'good',
            $score >= 550 => 'fair',
            default       => 'poor',
        };
    }

    // ─── Global factor calculators (0–100) ───────────────────────────────────

    private function computeRepaymentHistoryFactor(\Illuminate\Support\Collection $payments): int
    {
        $total = $payments->count();
        if ($total === 0) {
            return 50; // neutral
        }
        $onTime = $payments->where('is_overdue_payment', false)->count();
        return (int) round(($onTime / $total) * 100);
    }

    private function computeDebtLoadFactor(\Illuminate\Support\Collection $loans, Borrower $borrower): int
    {
        $outstanding = $loans->whereIn('status', ['active', 'disbursed'])->sum('outstanding_balance');
        $income      = (float) ($borrower->monthly_income ?? 0);
        $annualIncome = $income * 12;

        if ($annualIncome <= 0 || $outstanding <= 0) {
            return 80; // assume low debt
        }

        $ratio = $outstanding / $annualIncome;
        return (int) max(0, min(100, round((1 - min($ratio, 1)) * 100)));
    }

    private function computeHistoryLengthFactor(\Illuminate\Support\Collection $loans): int
    {
        $first = $loans->whereNotNull('disbursement_date')->sortBy('disbursement_date')->first();
        if (! $first) {
            return 0;
        }
        $months = (int) now()->diffInMonths($first->disbursement_date);
        return (int) min(100, round(($months / 60) * 100));
    }

    private function computeAccountMixFactor(\Illuminate\Support\Collection $loans): int
    {
        $types = $loans->pluck('loan_type_id')->unique()->count();
        return (int) min(100, $types * 25);
    }

    private function computeNewCreditFactor(\Illuminate\Support\Collection $loans): int
    {
        $recentApps = $loans->filter(
            fn ($l) => $l->created_at && $l->created_at->gte(now()->subMonths(6))
        )->count();

        // Fewer applications = better score (max at 0, zero at 5+)
        return (int) max(0, min(100, round((1 - min($recentApps, 5) / 5) * 100)));
    }
}
