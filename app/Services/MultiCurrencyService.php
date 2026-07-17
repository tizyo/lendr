<?php

namespace App\Services;

use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\Loan;
use Illuminate\Support\Carbon;

class MultiCurrencyService
{
    /**
     * Convert an amount from one currency to another using the most recent
     * exchange rate on or before the given date.
     */
    public function convert(float $amount, string $from, string $to, ?Carbon $date = null): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->rateFor($from, $to, $date);

        return round($amount * $rate, 2);
    }

    /**
     * Look up the exchange rate for a currency pair.
     * Tries direct pair first, then inverse.
     */
    public function rateFor(string $from, string $to, ?Carbon $date = null): float
    {
        $date ??= now();

        // Direct rate
        $rate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->value('rate');

        if ($rate !== null) {
            return (float) $rate;
        }

        // Try inverse
        $inverse = ExchangeRate::where('from_currency', $to)
            ->where('to_currency', $from)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->value('rate');

        if ($inverse !== null && (float) $inverse > 0) {
            return round(1 / (float) $inverse, 6);
        }

        // Same currency or no rate found — return 1
        return 1.0;
    }

    /**
     * Lock the current fx_rate onto a loan at disbursement time.
     */
    public function lockRateForLoan(Loan $loan): void
    {
        if ($loan->currency === $loan->base_currency) {
            $loan->update(['fx_rate' => 1.0]);
            return;
        }

        $rate = $this->rateFor($loan->currency, $loan->base_currency);
        $loan->update(['fx_rate' => $rate]);
    }

    /**
     * Get the outstanding balance expressed in the base currency.
     */
    public function outstandingInBase(Loan $loan): float
    {
        if ($loan->currency === $loan->base_currency) {
            return (float) $loan->outstanding_balance;
        }

        return round((float) $loan->outstanding_balance * (float) $loan->fx_rate, 2);
    }

    /**
     * Produce a portfolio summary in base currency.
     *
     * @return array{currency: string, total_outstanding: float, loans: array}
     */
    public function portfolioSummary(string $baseCurrency = 'ZMW'): array
    {
        $loans = Loan::whereIn('status', ['active', 'overdue', 'disbursed'])
            ->get();

        $total = 0.0;
        $breakdown = [];

        foreach ($loans->groupBy('currency') as $currency => $group) {
            $rate    = $this->rateFor($currency, $baseCurrency);
            $subtotal = $group->sum(fn ($l) => (float) $l->outstanding_balance * $rate);
            $total   += $subtotal;
            $breakdown[$currency] = [
                'count'        => $group->count(),
                'outstanding'  => round($group->sum('outstanding_balance'), 2),
                'in_base'      => round($subtotal, 2),
                'rate'         => $rate,
            ];
        }

        return [
            'base_currency'     => $baseCurrency,
            'total_outstanding' => round($total, 2),
            'by_currency'       => $breakdown,
        ];
    }
}
