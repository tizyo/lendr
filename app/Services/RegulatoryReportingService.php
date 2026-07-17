<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\GlAccount;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\RegulatoryReport;
use App\Services\Mail\TenantMailService;
use Illuminate\Support\Carbon;

class RegulatoryReportingService
{
    /** BOZ regulatory thresholds */
    private const CAR_MINIMUM          = 10.0;  // 10%
    private const LIQUIDITY_MINIMUM    = 20.0;  // 20%
    private const LARGE_EXPOSURE_LIMIT = 25.0;  // 25% of regulatory capital

    public function __construct(private TenantMailService $mail) {}

    // ─── CAR (Capital Adequacy Ratio) ─────────────────────────────────────────

    /**
     * CAR = Tier-1 Capital / Risk-Weighted Assets × 100
     * Risk-weighted assets = loans receivable (100% weight, simplified)
     */
    public function capitalAdequacyRatio(string $period): array
    {
        $capital    = $this->sumGlType('equity');
        $loanAssets = $this->sumGlByCode('1100'); // Loans Receivable

        // Fallback: if no GL data, use loans table
        if ($loanAssets <= 0) {
            $loanAssets = (float) Loan::whereIn('status', ['active', 'overdue'])
                ->sum('outstanding_balance');
        }

        $rwa = max($loanAssets, 0.01); // avoid division by zero
        $car = round(($capital / $rwa) * 100, 2);

        return [
            'period'               => $period,
            'tier_1_capital'       => round($capital, 2),
            'risk_weighted_assets' => round($rwa, 2),
            'car_pct'              => $car,
            'minimum_pct'          => self::CAR_MINIMUM,
            'compliant'            => $car >= self::CAR_MINIMUM,
        ];
    }

    // ─── Liquidity Ratio ──────────────────────────────────────────────────────

    /**
     * Liquidity = Liquid Assets (cash + bank) / Short-term Liabilities × 100
     */
    public function liquidityRatio(string $period): array
    {
        $cash        = $this->sumGlByCode('1001'); // Cash on Hand
        $bank        = $this->sumGlByCode('1002'); // Bank Account
        $liquidAssets = $cash + $bank;

        $liabilities = $this->sumGlType('liability');

        $ratio = $liabilities > 0 ? round(($liquidAssets / $liabilities) * 100, 2) : 100.0;

        return [
            'period'             => $period,
            'liquid_assets'      => round($liquidAssets, 2),
            'short_term_liabs'   => round($liabilities, 2),
            'liquidity_ratio_pct'=> $ratio,
            'minimum_pct'        => self::LIQUIDITY_MINIMUM,
            'compliant'          => $ratio >= self::LIQUIDITY_MINIMUM,
        ];
    }

    // ─── Large Exposure ───────────────────────────────────────────────────────

    /**
     * Single-borrower exposure > 25% of regulatory capital
     */
    public function largeExposures(string $period): array
    {
        $capital = max($this->sumGlType('equity'), 1);

        $borrowers = Loan::whereIn('status', ['active', 'overdue'])
            ->selectRaw('borrower_id, SUM(outstanding_balance) as total_exposure')
            ->groupBy('borrower_id')
            ->get();

        $exposures = [];
        foreach ($borrowers as $row) {
            $pct = round(((float) $row->total_exposure / $capital) * 100, 2);
            if ($pct >= self::LARGE_EXPOSURE_LIMIT) {
                $borrower = Borrower::find($row->borrower_id);
                $exposures[] = [
                    'borrower_id'     => $row->borrower_id,
                    'borrower_name'   => $borrower ? ($borrower->first_name . ' ' . $borrower->last_name) : '—',
                    'total_exposure'  => round((float) $row->total_exposure, 2),
                    'exposure_pct'    => $pct,
                    'limit_pct'       => self::LARGE_EXPOSURE_LIMIT,
                    'breached'        => $pct > self::LARGE_EXPOSURE_LIMIT,
                ];
            }
        }

        return [
            'period'            => $period,
            'regulatory_capital' => round($capital, 2),
            'limit_pct'         => self::LARGE_EXPOSURE_LIMIT,
            'exposures'         => $exposures,
        ];
    }

    // ─── PAR (Portfolio at Risk) ──────────────────────────────────────────────

    /**
     * PAR30 / PAR60 / PAR90 buckets
     */
    public function portfolioAtRisk(string $period): array
    {
        $totalPortfolio = (float) Loan::whereIn('status', ['active', 'overdue'])
            ->sum('outstanding_balance');

        if ($totalPortfolio <= 0) {
            return [
                'period'          => $period,
                'total_portfolio' => 0,
                'par_30'          => ['amount' => 0, 'pct' => 0],
                'par_60'          => ['amount' => 0, 'pct' => 0],
                'par_90'          => ['amount' => 0, 'pct' => 0],
            ];
        }

        $par30  = $this->parBucket(30, 59);
        $par60  = $this->parBucket(60, 89);
        $par90  = $this->parBucket(90, null);

        return [
            'period'          => $period,
            'total_portfolio' => round($totalPortfolio, 2),
            'par_30'          => [
                'amount' => round($par30, 2),
                'pct'    => round(($par30 / $totalPortfolio) * 100, 2),
            ],
            'par_60'          => [
                'amount' => round($par60, 2),
                'pct'    => round(($par60 / $totalPortfolio) * 100, 2),
            ],
            'par_90'          => [
                'amount' => round($par90, 2),
                'pct'    => round(($par90 / $totalPortfolio) * 100, 2),
            ],
        ];
    }

    // ─── Generate & persist ───────────────────────────────────────────────────

    public function generate(string $type, string $period): RegulatoryReport
    {
        $data = match ($type) {
            'car'            => $this->capitalAdequacyRatio($period),
            'liquidity'      => $this->liquidityRatio($period),
            'large_exposure' => $this->largeExposures($period),
            'par'            => $this->portfolioAtRisk($period),
            default          => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };

        return RegulatoryReport::updateOrCreate(
            ['report_type' => $type, 'period' => $period],
            [
                'data'         => $data,
                'generated_by' => auth()->user()?->name ?? 'system',
                'emailed'      => false,
            ]
        );
    }

    /**
     * Email a generated report to recipients.
     */
    public function email(RegulatoryReport $report, array $recipients): void
    {
        $subject = '[Regulatory Report] ' . strtoupper($report->report_type) . ' — ' . $report->period;
        $body    = "Please find the {$report->report_type} regulatory report for period {$report->period} attached below.\n\n"
                 . json_encode($report->data, JSON_PRETTY_PRINT);

        foreach ($recipients as $to) {
            $this->mail->raw($to, $subject, $body);
        }

        $report->update(['emailed' => true, 'emailed_at' => now()]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function sumGlType(string $type): float
    {
        $accounts = GlAccount::where('type', $type)->where('is_active', true)->get();
        return (float) $accounts->sum(fn ($a) => $a->balance());
    }

    private function sumGlByCode(string $code): float
    {
        $account = GlAccount::where('code', $code)->where('is_active', true)->first();
        return $account ? $account->balance() : 0.0;
    }

    private function parBucket(int $minDays, ?int $maxDays): float
    {
        $loanIds = LoanSchedule::whereNull('paid_at')
            ->whereDate('due_date', '<', now())
            ->where(function ($q) use ($minDays, $maxDays) {
                $q->whereRaw(
                    'CAST(julianday("now") - julianday(due_date) AS INTEGER) >= ?',
                    [$minDays]
                );
                if ($maxDays !== null) {
                    $q->whereRaw(
                        'CAST(julianday("now") - julianday(due_date) AS INTEGER) <= ?',
                        [$maxDays]
                    );
                }
            })
            ->distinct()
            ->pluck('loan_id');

        return (float) Loan::whereIn('id', $loanIds)
            ->whereIn('status', ['active', 'overdue'])
            ->sum('outstanding_balance');
    }
}
