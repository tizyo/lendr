<?php

namespace App\Services;

use App\Models\Tenant\Investor;
use App\Models\Tenant\InvestorAllocation;
use App\Models\Tenant\InvestorDividend;
use Carbon\Carbon;

class InvestorReturnsService
{
    /** Default withholding tax rate (15%) */
    const TAX_RATE = 0.15;

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

        $totalPrincipal = (float) $allocations->sum('allocated_amount');

        // Monthly gross dividend = principal × annual_rate / 12
        $grossDividend = round($totalPrincipal * ($annualRatePct / 100) / 12, 2);
        $taxWithheld   = round($grossDividend * self::TAX_RATE, 2);
        $netDividend   = round($grossDividend - $taxWithheld, 2);

        return InvestorDividend::create([
            'investor_id'    => $investor->id,
            'allocation_id'  => $allocationId,
            'period'         => $period,
            'principal'      => $totalPrincipal,
            'return_rate'    => $annualRatePct,
            'gross_dividend' => $grossDividend,
            'tax_withheld'   => $taxWithheld,
            'net_dividend'   => $netDividend,
            'status'         => 'pending',
        ]);
    }

    /**
     * Mark a dividend as paid.
     */
    public function markPaid(InvestorDividend $dividend, int $processedBy): InvestorDividend
    {
        $dividend->update([
            'status'       => 'paid',
            'paid_date'    => now()->toDateString(),
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
                $alloc->increment('actual_return', round($dividend->net_dividend / max($allocations->count(), 1), 2));
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

        return [
            'total_paid'    => (float) $dividends->where('status', 'paid')->sum('net_dividend'),
            'total_pending' => (float) $dividends->where('status', 'pending')->sum('net_dividend'),
            'total_gross'   => (float) $dividends->sum('gross_dividend'),
            'total_tax'     => (float) $dividends->sum('tax_withheld'),
            'count'         => $dividends->count(),
        ];
    }
}
