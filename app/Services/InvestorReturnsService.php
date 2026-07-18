<?php

namespace App\Services;

use App\Models\Tenant\Investor;
use App\Models\Tenant\InvestorAllocation;
use App\Models\Tenant\InvestorDividend;
use App\Traits\UsesBcMath;

class InvestorReturnsService
{
    use UsesBcMath;

    /** Default withholding tax rate (15%) */
    const TAX_RATE = '0.15';

    /**
     * Calculate and create a dividend record for an investor for the given period.
     * Period format: "YYYY-MM"
     *
     * @param  float  $annualRatePct  Annual return rate % (e.g. 12.0 for 12%)
     */
    public function calculateDividend(Investor $investor, string $period, float $annualRatePct = 12.0, ?int $allocationId = null): InvestorDividend
    {
        $query = $investor->allocations()->where('status', 'active');
        if ($allocationId) {
            $query->where('id', $allocationId);
        }

        $allocations = $query->get();

        $totalPrincipal = (float) $allocations->reduce(
            fn ($carry, $a) => bcadd($carry, (string) $a->allocated_amount, 2),
            '0'
        );

        // Monthly gross dividend = principal × annual_rate / 12
        $grossDividendStr = bcdiv(bcmul((string) $totalPrincipal, bcdiv((string) $annualRatePct, '100', 10), 10), '12', 10);
        $taxWithheldStr = bcmul($grossDividendStr, self::TAX_RATE, 10);
        $grossDividend = (float) $this->bcround($grossDividendStr);
        $taxWithheld = (float) $this->bcround($taxWithheldStr);
        $netDividend = (float) $this->bcround(bcsub($grossDividendStr, $taxWithheldStr, 10));

        return InvestorDividend::create([
            'investor_id' => $investor->id,
            'allocation_id' => $allocationId,
            'period' => $period,
            'principal' => $totalPrincipal,
            'return_rate' => $annualRatePct,
            'gross_dividend' => $grossDividend,
            'tax_withheld' => $taxWithheld,
            'net_dividend' => $netDividend,
            'status' => 'pending',
        ]);
    }

    /**
     * Mark a dividend as paid.
     */
    public function markPaid(InvestorDividend $dividend, int $processedBy): InvestorDividend
    {
        $dividend->update([
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
            'processed_by' => $processedBy,
        ]);

        // Update actual_return on the related allocation(s)
        if ($dividend->allocation_id) {
            $allocation = InvestorAllocation::find($dividend->allocation_id);
            if ($allocation) {
                $allocation->increment('actual_return', $dividend->net_dividend);
            }
        } else {
            // Apply net dividend proportionally to all active allocations
            $allocations = InvestorAllocation::where('investor_id', $dividend->investor_id)
                ->where('status', 'active')
                ->get();

            foreach ($allocations as $alloc) {
                $share = (float) $this->bcround(bcdiv((string) $dividend->net_dividend, (string) max($allocations->count(), 1), 10));
                $alloc->increment('actual_return', $share);
            }
        }

        return $dividend->fresh();
    }

    /**
     * Returns summary of total dividends paid / pending for an investor.
     */
    public function summary(Investor $investor): array
    {
        $dividends = $investor->dividends;
        $bcSum = fn ($collection, $column) => (float) $collection->reduce(
            fn ($carry, $d) => bcadd($carry, (string) $d->{$column}, 2),
            '0'
        );

        return [
            'total_paid' => $bcSum($dividends->where('status', 'paid'), 'net_dividend'),
            'total_pending' => $bcSum($dividends->where('status', 'pending'), 'net_dividend'),
            'total_gross' => $bcSum($dividends, 'gross_dividend'),
            'total_tax' => $bcSum($dividends, 'tax_withheld'),
            'count' => $dividends->count(),
        ];
    }
}
