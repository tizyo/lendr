<?php

namespace App\Services;

use App\Models\Tenant\Payment;
use App\Models\Tenant\TaxComputation;
use App\Models\Tenant\TaxConfiguration;
use Carbon\Carbon;

class TaxComplianceService
{
    /**
     * Compute WHT on a payment's interest component and persist it.
     * Call this after a payment is recorded.
     */
    public function computeWhtForPayment(Payment $payment): ?TaxComputation
    {
        $wht = TaxConfiguration::activeWht();

        if (! $wht || ! $wht->applies_to_interest) {
            return null;
        }

        $interestPaid = (float) ($payment->interest_allocated ?? 0);

        if ($interestPaid <= 0) {
            return null;
        }

        $taxAmount = round($interestPaid * $wht->rate / 100, 2);
        $period = Carbon::parse($payment->payment_date)->format('Y-m');

        // Avoid duplicate
        $existing = TaxComputation::where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('tax_configuration_id', $wht->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return TaxComputation::create([
            'tax_configuration_id' => $wht->id,
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'taxable_amount' => $interestPaid,
            'tax_amount' => $taxAmount,
            'period' => $period,
            'status' => 'computed',
        ]);
    }

    /**
     * Monthly WHT summary: total taxable interest & tax per period.
     */
    public function whtSummary(string $fromPeriod, string $toPeriod): array
    {
        $rows = TaxComputation::join('tax_configurations', 'tax_configurations.id', '=', 'tax_computations.tax_configuration_id')
            ->where('tax_configurations.tax_type', 'wht')
            ->whereBetween('tax_computations.period', [$fromPeriod, $toPeriod])
            ->selectRaw('tax_computations.period, SUM(taxable_amount) as total_taxable, SUM(tax_amount) as total_tax, tax_computations.status')
            ->groupBy('tax_computations.period', 'tax_computations.status')
            ->orderBy('tax_computations.period')
            ->get();

        return $rows->map(fn ($r) => [
            'period' => $r->period,
            'total_taxable' => (float) $r->total_taxable,
            'total_tax' => (float) $r->total_tax,
            'status' => $r->status,
        ])->values()->all();
    }

    /**
     * Mark all computed entries for a period as remitted.
     */
    public function markRemitted(string $period): int
    {
        return TaxComputation::where('period', $period)
            ->where('status', 'computed')
            ->update(['status' => 'remitted', 'remitted_at' => now()]);
    }

    /**
     * Portfolio-at-risk (PAR) regulatory report.
     * Returns PAR buckets: 1-30, 31-60, 61-90, 90+ days past due.
     */
    public function parReport(): array
    {
        $loans = \App\Models\Tenant\Loan::where('status', 'active')
            ->with('schedule')
            ->get();

        $buckets = ['par_1_30' => 0, 'par_31_60' => 0, 'par_61_90' => 0, 'par_90plus' => 0];
        $totalOutstanding = 0;

        foreach ($loans as $loan) {
            $oldest = $loan->schedule
                ->where('is_paid', false)
                ->where('due_date', '<', now()->toDateString())
                ->sortBy('due_date')
                ->first();

            if (! $oldest) {
                continue;
            }

            $dpd = Carbon::parse($oldest->due_date)->diffInDays(now());
            $outstanding = (float) ($loan->outstanding_balance ?? 0);
            $totalOutstanding += $outstanding;

            if ($dpd <= 30) {
                $buckets['par_1_30'] += $outstanding;
            } elseif ($dpd <= 60) {
                $buckets['par_31_60'] += $outstanding;
            } elseif ($dpd <= 90) {
                $buckets['par_61_90'] += $outstanding;
            } else {
                $buckets['par_90plus'] += $outstanding;
            }
        }

        $totalPar = array_sum($buckets);

        return [
            'generated_at' => now()->toDateTimeString(),
            'total_portfolio' => $totalOutstanding,
            'total_par' => $totalPar,
            'par_ratio' => $totalOutstanding > 0 ? round($totalPar / $totalOutstanding * 100, 2) : 0,
            'buckets' => $buckets,
        ];
    }

    /**
     * Capital adequacy overview — total loan book vs total fund.
     */
    public function capitalAdequacy(): array
    {
        $totalLoans = \App\Models\Tenant\Loan::where('status', 'active')
            ->sum('outstanding_balance');

        $fundRow = \App\Models\Tenant\FundBalance::current();
        $totalFund = (float) ($fundRow?->available_balance ?? 0);

        $ratio = $totalFund > 0 ? round((float) $totalLoans / $totalFund * 100, 2) : null;

        return [
            'total_loan_book' => (float) $totalLoans,
            'total_fund' => $totalFund,
            'exposure_ratio_pct' => $ratio,
        ];
    }
}
