<?php

namespace App\Services;

use App\Enums\RepaymentSchedule;
use App\Models\Tenant\LoanPlan;
use Carbon\Carbon;

/**
 * Calculates loan amounts and generates repayment schedules.
 *
 * Supports flat, reducing-balance, and compound interest types.
 * Supports daily, weekly, bi-weekly, monthly, and bullet repayment schedules.
 */
class LoanCalculatorService
{
    /**
     * Normalise repayment_schedule to a plain string regardless of whether
     * a RepaymentSchedule enum or raw string is provided.
     */
    private function sched(mixed $value): string
    {
        return $value instanceof RepaymentSchedule ? $value->value : (string) $value;
    }

    /**
     * Full calculation: amounts + schedule preview.
     */
    public function calculate(LoanPlan $plan, float $principal, int $tenure, string $disbursementDate): array
    {
        $amounts  = $this->calculateAmounts($plan, $principal, $tenure);
        $schedule = $this->generateSchedule($plan, $principal, $tenure, $disbursementDate, $amounts['interest_amount']);

        return array_merge($amounts, ['schedule' => $schedule]);
    }

    /**
     * Calculate loan totals (interest, fees, total payable).
     */
    public function calculateAmounts(LoanPlan $plan, float $principal, int $tenure): array
    {
        $interestAmount = $this->computeInterest($plan, $principal, $tenure);
        $processingFee  = round($principal * ($plan->processing_fee / 100), 2);
        $insuranceFee   = round($principal * ($plan->insurance_fee / 100), 2);
        $totalPayable   = round($principal + $interestAmount + $processingFee + $insuranceFee, 2);

        return [
            'principal_amount' => round($principal, 2),
            'interest_amount'  => round($interestAmount, 2),
            'processing_fee'   => $processingFee,
            'insurance_fee'    => $insuranceFee,
            'total_payable'    => $totalPayable,
        ];
    }

    /**
     * Generate instalment schedule rows.
     */
    public function generateSchedule(LoanPlan $plan, float $principal, int $tenure, string $disbursementDate, float $interestAmount): array
    {
        $disbursed = Carbon::parse($disbursementDate);
        $schedule  = [];
        $schedStr  = $this->sched($plan->repayment_schedule);

        if ($schedStr === 'bullet') {
            // Single lump-sum payment at maturity
            $dueDate = $this->addPeriod($disbursed, $schedStr, 1);
            $total   = round($principal + $interestAmount, 2);

            $schedule[] = [
                'instalment_number' => 1,
                'due_date'          => $dueDate->toDateString(),
                'principal_due'     => round($principal, 2),
                'interest_due'      => round($interestAmount, 2),
                'fee_due'           => 0.00,
                'total_due'         => $total,
                'outstanding'       => $total,
            ];

            return $schedule;
        }

        $instalments = $tenure; // tenure = number of periods for periodic schedules

        switch ($plan->interest_type) {
            case 'flat':
                $schedule = $this->flatSchedule($plan, $principal, $instalments, $interestAmount, $disbursed, $schedStr);
                break;

            case 'reducing_balance':
                $schedule = $this->reducingBalanceSchedule($plan, $principal, $instalments, $disbursed, $schedStr);
                break;

            case 'compound':
                $schedule = $this->compoundSchedule($plan, $principal, $instalments, $disbursed, $schedStr);
                break;
        }

        return $schedule;
    }

    // ─── Interest Computation ────────────────────────────────────────────────

    private function computeInterest(LoanPlan $plan, float $principal, int $tenure): float
    {
        $rate  = $plan->interest_rate / 100;
        $sched = $this->sched($plan->repayment_schedule);

        return match ($plan->interest_type) {
            'flat' => $this->flatInterest($rate, $principal, $tenure, $plan->interest_period, $sched, $plan->tenure_type),

            'reducing_balance' => $this->reducingBalanceInterest($rate, $principal, $tenure, $plan->interest_period, $sched),

            'compound' => $this->compoundInterest($rate, $principal, $tenure, $plan->interest_period, $sched),

            default => 0.0,
        };
    }

    private function flatInterest(float $rate, float $principal, int $tenure, string $interestPeriod, string $repaymentSchedule, string $tenureType): float
    {
        // Normalise to number of repayment periods
        $periods = $repaymentSchedule === 'bullet' ? 1 : $tenure;

        // Convert tenure to the interest period unit
        $periodsInInterestUnit = $this->convertTenureToPeriodUnit($tenure, $tenureType, $interestPeriod, $repaymentSchedule);

        return $principal * $rate * $periodsInInterestUnit;
    }

    private function reducingBalanceInterest(float $rate, float $principal, int $instalments, string $interestPeriod, string $repaymentSchedule): float
    {
        if ($repaymentSchedule === 'bullet') {
            return $principal * $rate; // one period
        }

        $periodRate = $this->periodRate($rate, $interestPeriod, $repaymentSchedule);
        $emi        = $this->emi($principal, $periodRate, $instalments);
        return round(($emi * $instalments) - $principal, 2);
    }

    private function compoundInterest(float $rate, float $principal, int $instalments, string $interestPeriod, string $repaymentSchedule): float
    {
        if ($repaymentSchedule === 'bullet') {
            return round($principal * $rate, 2);
        }

        // For compound: use EMI formula — same as reducing balance
        $periodRate = $this->periodRate($rate, $interestPeriod, $repaymentSchedule);
        $emi        = $this->emi($principal, $periodRate, $instalments);
        return round(($emi * $instalments) - $principal, 2);
    }

    // ─── Schedule Builders ───────────────────────────────────────────────────

    private function flatSchedule(LoanPlan $plan, float $principal, int $instalments, float $totalInterest, Carbon $disbursed, string $schedStr): array
    {
        $principalPerInstalment = round($principal / $instalments, 2);
        $interestPerInstalment  = round($totalInterest / $instalments, 2);
        $schedule = [];
        $balance  = $principal;

        for ($i = 1; $i <= $instalments; $i++) {
            $dueDate = $this->addPeriod($disbursed, $schedStr, $i);

            // Last instalment absorbs rounding
            $principalDue = ($i === $instalments)
                ? round($balance, 2)
                : $principalPerInstalment;

            $interestDue = ($i === $instalments)
                ? round($totalInterest - ($interestPerInstalment * ($instalments - 1)), 2)
                : $interestPerInstalment;

            $totalDue = round($principalDue + $interestDue, 2);
            $balance  = round($balance - $principalDue, 2);

            $schedule[] = [
                'instalment_number' => $i,
                'due_date'          => $dueDate->toDateString(),
                'principal_due'     => $principalDue,
                'interest_due'      => $interestDue,
                'fee_due'           => 0.00,
                'total_due'         => $totalDue,
                'outstanding'       => $totalDue,
            ];
        }

        return $schedule;
    }

    private function reducingBalanceSchedule(LoanPlan $plan, float $principal, int $instalments, Carbon $disbursed, string $schedStr): array
    {
        $rate     = $plan->interest_rate / 100;
        $schedule = [];
        $balance  = $principal;

        // If interest period differs from repayment period, normalise
        $periodRate = $this->periodRate($rate, $plan->interest_period, $schedStr);
        $emi        = $this->emi($principal, $periodRate, $instalments);

        for ($i = 1; $i <= $instalments; $i++) {
            $dueDate     = $this->addPeriod($disbursed, $schedStr, $i);
            $interestDue = round($balance * $periodRate, 2);
            $principalDue = ($i === $instalments)
                ? round($balance, 2)
                : round($emi - $interestDue, 2);

            $principalDue = min($principalDue, $balance);
            $totalDue     = round($principalDue + $interestDue, 2);
            $balance      = round($balance - $principalDue, 2);

            $schedule[] = [
                'instalment_number' => $i,
                'due_date'          => $dueDate->toDateString(),
                'principal_due'     => $principalDue,
                'interest_due'      => $interestDue,
                'fee_due'           => 0.00,
                'total_due'         => $totalDue,
                'outstanding'       => $totalDue,
            ];
        }

        return $schedule;
    }

    private function compoundSchedule(LoanPlan $plan, float $principal, int $instalments, Carbon $disbursed, string $schedStr): array
    {
        // Compound uses same EMI formula as reducing balance
        return $this->reducingBalanceSchedule($plan, $principal, $instalments, $disbursed, $schedStr);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Equated Monthly Instalment (EMI) formula.
     * Also used for other period EMIs by providing the appropriate period rate.
     */
    private function emi(float $principal, float $periodRate, int $n): float
    {
        if ($periodRate == 0) {
            return round($principal / $n, 2);
        }

        return round($principal * $periodRate * pow(1 + $periodRate, $n) / (pow(1 + $periodRate, $n) - 1), 2);
    }

    /**
     * Convert an annual/monthly/etc. rate to the repayment period rate.
     */
    private function periodRate(float $annualOrPeriodRate, string $interestPeriod, string $repaymentSchedule): float
    {
        // First convert interest rate to daily rate
        $dailyRate = match ($interestPeriod) {
            'daily'    => $annualOrPeriodRate,
            'weekly'   => $annualOrPeriodRate / 7,
            'monthly'  => $annualOrPeriodRate / 30,
            'annually' => $annualOrPeriodRate / 365,
            default    => $annualOrPeriodRate / 30,
        };

        // Then convert to repayment period rate
        return match ($repaymentSchedule) {
            'daily'     => $dailyRate,
            'weekly'    => $dailyRate * 7,
            'bi_weekly' => $dailyRate * 14,
            'monthly'   => $dailyRate * 30,
            default     => $dailyRate * 30,
        };
    }

    /**
     * Convert the loan tenure to the number of interest periods.
     */
    private function convertTenureToPeriodUnit(int $tenure, string $tenureType, string $interestPeriod, string $repaymentSchedule): float
    {
        if ($repaymentSchedule === 'bullet') {
            // For bullet, 1 period of the interest period type
            return 1;
        }

        // Convert tenure to days first
        $days = match ($tenureType) {
            'days'   => $tenure,
            'weeks'  => $tenure * 7,
            'months' => $tenure * 30,
            default  => $tenure * 30,
        };

        // Convert days to the interest period unit
        return match ($interestPeriod) {
            'daily'    => $days,
            'weekly'   => $days / 7,
            'monthly'  => $days / 30,
            'annually' => $days / 365,
            default    => $days / 30,
        };
    }

    /**
     * Add N repayment periods to the disbursement date.
     */
    private function addPeriod(Carbon $base, string $repaymentSchedule, int $n): Carbon
    {
        $clone = $base->copy();

        return match ($repaymentSchedule) {
            'daily'     => $clone->addDays($n),
            'weekly'    => $clone->addWeeks($n),
            'bi_weekly' => $clone->addWeeks($n * 2),
            'monthly'   => $clone->addMonths($n),
            'bullet'    => $clone->addMonths($n), // maturity date = 1 period from disbursement
            default     => $clone->addMonths($n),
        };
    }

    /**
     * Preview amortization without a LoanPlan record — accepts raw parameters.
     * Useful for borrower portal "calculate before applying".
     *
     * @param array{
     *   principal: float,
     *   interest_rate: float,
     *   interest_type: string,
     *   interest_period: string,
     *   tenure: int,
     *   tenure_type: string,
     *   repayment_schedule: string,
     *   processing_fee?: float,
     *   insurance_fee?: float,
     *   start_date?: string,
     * } $params
     */
    public function preview(array $params): array
    {
        $plan = new LoanPlan();
        $plan->forceFill([
            'interest_rate'      => $params['interest_rate'],
            'interest_type'      => $params['interest_type'],
            'interest_period'    => $params['interest_period'],
            'tenure_type'        => $params['tenure_type'],
            'repayment_schedule' => $params['repayment_schedule'],
            'processing_fee'     => $params['processing_fee'] ?? 0,
            'insurance_fee'      => $params['insurance_fee']  ?? 0,
        ]);

        $startDate = $params['start_date'] ?? now()->toDateString();

        return $this->calculate($plan, (float) $params['principal'], (int) $params['tenure'], $startDate);
    }

    /**
     * Compute maturity date for a loan.
     */
    public function maturityDate(string $disbursementDate, LoanPlan $plan, int $tenure): string
    {
        $disbursed = Carbon::parse($disbursementDate);
        $schedStr  = $this->sched($plan->repayment_schedule);

        if ($schedStr === 'bullet') {
            // Tenure in tenure_type units
            return match ($plan->tenure_type) {
                'days'   => $disbursed->addDays($tenure)->toDateString(),
                'weeks'  => $disbursed->addWeeks($tenure)->toDateString(),
                'months' => $disbursed->addMonths($tenure)->toDateString(),
                default  => $disbursed->addMonths($tenure)->toDateString(),
            };
        }

        // For periodic schedules: last instalment date
        return $this->addPeriod($disbursed, $schedStr, $tenure)->toDateString();
    }
}
