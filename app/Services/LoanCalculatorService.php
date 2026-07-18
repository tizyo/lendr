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
 *
 * All money math is done with BCMath on string values to avoid float
 * imprecision. RATE_SCALE is used for intermediate (rate/ratio) working
 * precision; every value that becomes a reported/stored amount is rounded
 * to SCALE (2dp, round-half-up) via bcround() before it leaves this class.
 */
class LoanCalculatorService
{
    private const SCALE = 2;

    private const RATE_SCALE = 10;

    /**
     * Round-half-up a BCMath string to $scale decimal places. Native bcmath
     * scale truncates rather than rounds, so this adds a half-unit at the
     * target precision before truncating.
     */
    private function bcround(string $num, int $scale = self::SCALE): string
    {
        $isNeg = str_starts_with($num, '-');
        $abs = $isNeg ? substr($num, 1) : $num;

        $increment = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);
        $rounded = bcadd($abs, $increment, $scale);

        return $isNeg && bccomp($rounded, '0', $scale) !== 0 ? '-'.$rounded : $rounded;
    }

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
        $amounts = $this->calculateAmounts($plan, $principal, $tenure);
        $schedule = $this->generateSchedule($plan, $principal, $tenure, $disbursementDate, $amounts['interest_amount']);

        return array_merge($amounts, ['schedule' => $schedule]);
    }

    /**
     * Calculate loan totals (interest, fees, total payable).
     */
    public function calculateAmounts(LoanPlan $plan, float $principal, int $tenure): array
    {
        $principalStr = (string) $principal;

        $interestAmount = $this->bcround($this->computeInterest($plan, $principalStr, $tenure));
        $processingFee = $this->bcround(bcmul($principalStr, bcdiv((string) $plan->processing_fee, '100', self::RATE_SCALE), self::RATE_SCALE));
        $insuranceFee = $this->bcround(bcmul($principalStr, bcdiv((string) $plan->insurance_fee, '100', self::RATE_SCALE), self::RATE_SCALE));

        $totalPayable = $this->bcround(
            bcadd(bcadd(bcadd($principalStr, $interestAmount, self::RATE_SCALE), $processingFee, self::RATE_SCALE), $insuranceFee, self::RATE_SCALE),
        );

        return [
            'principal_amount' => (float) $this->bcround($principalStr),
            'interest_amount' => (float) $interestAmount,
            'processing_fee' => (float) $processingFee,
            'insurance_fee' => (float) $insuranceFee,
            'total_payable' => (float) $totalPayable,
        ];
    }

    /**
     * Generate instalment schedule rows.
     */
    public function generateSchedule(LoanPlan $plan, float $principal, int $tenure, string $disbursementDate, float $interestAmount): array
    {
        $disbursed = Carbon::parse($disbursementDate);
        $schedule = [];
        $schedStr = $this->sched($plan->repayment_schedule);
        $principalStr = (string) $principal;
        $interestStr = (string) $interestAmount;

        if ($schedStr === 'bullet') {
            // Single lump-sum payment at maturity
            $dueDate = $this->addPeriod($disbursed, $schedStr, 1);
            $total = $this->bcround(bcadd($principalStr, $interestStr, self::RATE_SCALE));

            $schedule[] = [
                'instalment_number' => 1,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => (float) $this->bcround($principalStr),
                'interest_due' => (float) $this->bcround($interestStr),
                'fee_due' => 0.00,
                'total_due' => (float) $total,
                'outstanding' => (float) $total,
            ];

            return $schedule;
        }

        $instalments = $tenure; // tenure = number of periods for periodic schedules

        switch ($plan->interest_type) {
            case 'flat':
                $schedule = $this->flatSchedule($plan, $principalStr, $instalments, $interestStr, $disbursed, $schedStr);
                break;

            case 'reducing_balance':
                $schedule = $this->reducingBalanceSchedule($plan, $principalStr, $instalments, $disbursed, $schedStr);
                break;

            case 'compound':
                $schedule = $this->compoundSchedule($plan, $principalStr, $instalments, $disbursed, $schedStr);
                break;
        }

        return $schedule;
    }

    // ─── Interest Computation ────────────────────────────────────────────────

    private function computeInterest(LoanPlan $plan, string $principal, int $tenure): string
    {
        $rate = bcdiv((string) $plan->interest_rate, '100', self::RATE_SCALE);
        $sched = $this->sched($plan->repayment_schedule);

        return match ($plan->interest_type) {
            'flat' => $this->flatInterest($rate, $principal, $tenure, $plan->interest_period, $sched, $plan->tenure_type),

            'reducing_balance' => $this->reducingBalanceInterest($rate, $principal, $tenure, $plan->interest_period, $sched),

            'compound' => $this->compoundInterest($rate, $principal, $tenure, $plan->interest_period, $sched),

            default => '0',
        };
    }

    private function flatInterest(string $rate, string $principal, int $tenure, string $interestPeriod, string $repaymentSchedule, string $tenureType): string
    {
        // Convert tenure to the interest period unit
        $periodsInInterestUnit = $this->convertTenureToPeriodUnit($tenure, $tenureType, $interestPeriod, $repaymentSchedule);

        return bcmul(bcmul($principal, $rate, self::RATE_SCALE), $periodsInInterestUnit, self::RATE_SCALE);
    }

    private function reducingBalanceInterest(string $rate, string $principal, int $instalments, string $interestPeriod, string $repaymentSchedule): string
    {
        if ($repaymentSchedule === 'bullet') {
            return bcmul($principal, $rate, self::RATE_SCALE); // one period
        }

        $periodRate = $this->periodRate($rate, $interestPeriod, $repaymentSchedule);
        $emi = $this->emi($principal, $periodRate, $instalments);

        return $this->bcround(bcsub(bcmul($emi, (string) $instalments, self::RATE_SCALE), $principal, self::RATE_SCALE));
    }

    private function compoundInterest(string $rate, string $principal, int $instalments, string $interestPeriod, string $repaymentSchedule): string
    {
        if ($repaymentSchedule === 'bullet') {
            return $this->bcround(bcmul($principal, $rate, self::RATE_SCALE));
        }

        // For compound: use EMI formula — same as reducing balance
        $periodRate = $this->periodRate($rate, $interestPeriod, $repaymentSchedule);
        $emi = $this->emi($principal, $periodRate, $instalments);

        return $this->bcround(bcsub(bcmul($emi, (string) $instalments, self::RATE_SCALE), $principal, self::RATE_SCALE));
    }

    // ─── Schedule Builders ───────────────────────────────────────────────────

    private function flatSchedule(LoanPlan $plan, string $principal, int $instalments, string $totalInterest, Carbon $disbursed, string $schedStr): array
    {
        $principalPerInstalment = $this->bcround(bcdiv($principal, (string) $instalments, self::RATE_SCALE));
        $interestPerInstalment = $this->bcround(bcdiv($totalInterest, (string) $instalments, self::RATE_SCALE));
        $schedule = [];
        $balance = $principal;

        for ($i = 1; $i <= $instalments; $i++) {
            $dueDate = $this->addPeriod($disbursed, $schedStr, $i);

            // Last instalment absorbs rounding
            $principalDue = ($i === $instalments)
                ? $this->bcround($balance)
                : $principalPerInstalment;

            $interestDue = ($i === $instalments)
                ? $this->bcround(bcsub($totalInterest, bcmul($interestPerInstalment, (string) ($instalments - 1), self::RATE_SCALE), self::RATE_SCALE))
                : $interestPerInstalment;

            $totalDue = $this->bcround(bcadd($principalDue, $interestDue, self::RATE_SCALE));
            $balance = $this->bcround(bcsub($balance, $principalDue, self::RATE_SCALE));

            $schedule[] = [
                'instalment_number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => (float) $principalDue,
                'interest_due' => (float) $interestDue,
                'fee_due' => 0.00,
                'total_due' => (float) $totalDue,
                'outstanding' => (float) $totalDue,
            ];
        }

        return $schedule;
    }

    private function reducingBalanceSchedule(LoanPlan $plan, string $principal, int $instalments, Carbon $disbursed, string $schedStr): array
    {
        $rate = bcdiv((string) $plan->interest_rate, '100', self::RATE_SCALE);
        $schedule = [];
        $balance = $principal;

        // If interest period differs from repayment period, normalise
        $periodRate = $this->periodRate($rate, $plan->interest_period, $schedStr);
        $emi = $this->emi($principal, $periodRate, $instalments);

        for ($i = 1; $i <= $instalments; $i++) {
            $dueDate = $this->addPeriod($disbursed, $schedStr, $i);
            $interestDue = $this->bcround(bcmul($balance, $periodRate, self::RATE_SCALE));
            $principalDue = ($i === $instalments)
                ? $this->bcround($balance)
                : $this->bcround(bcsub($emi, $interestDue, self::RATE_SCALE));

            if (bccomp($principalDue, $balance, self::SCALE) > 0) {
                $principalDue = $this->bcround($balance);
            }

            $totalDue = $this->bcround(bcadd($principalDue, $interestDue, self::RATE_SCALE));
            $balance = $this->bcround(bcsub($balance, $principalDue, self::RATE_SCALE));

            $schedule[] = [
                'instalment_number' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => (float) $principalDue,
                'interest_due' => (float) $interestDue,
                'fee_due' => 0.00,
                'total_due' => (float) $totalDue,
                'outstanding' => (float) $totalDue,
            ];
        }

        return $schedule;
    }

    private function compoundSchedule(LoanPlan $plan, string $principal, int $instalments, Carbon $disbursed, string $schedStr): array
    {
        // Compound uses same EMI formula as reducing balance
        return $this->reducingBalanceSchedule($plan, $principal, $instalments, $disbursed, $schedStr);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Equated Monthly Instalment (EMI) formula.
     * Also used for other period EMIs by providing the appropriate period rate.
     */
    private function emi(string $principal, string $periodRate, int $n): string
    {
        if (bccomp($periodRate, '0', self::RATE_SCALE) === 0) {
            return $this->bcround(bcdiv($principal, (string) $n, self::RATE_SCALE));
        }

        $onePlusR = bcadd('1', $periodRate, self::RATE_SCALE);
        $pow = bcpow($onePlusR, (string) $n, self::RATE_SCALE);
        $numerator = bcmul(bcmul($principal, $periodRate, self::RATE_SCALE), $pow, self::RATE_SCALE);
        $denominator = bcsub($pow, '1', self::RATE_SCALE);

        return $this->bcround(bcdiv($numerator, $denominator, self::RATE_SCALE));
    }

    /**
     * Convert an annual/monthly/etc. rate to the repayment period rate.
     */
    private function periodRate(string $annualOrPeriodRate, string $interestPeriod, string $repaymentSchedule): string
    {
        // First convert interest rate to daily rate
        $dailyRate = match ($interestPeriod) {
            'daily' => $annualOrPeriodRate,
            'weekly' => bcdiv($annualOrPeriodRate, '7', self::RATE_SCALE),
            'monthly' => bcdiv($annualOrPeriodRate, '30', self::RATE_SCALE),
            'annually' => bcdiv($annualOrPeriodRate, '365', self::RATE_SCALE),
            default => bcdiv($annualOrPeriodRate, '30', self::RATE_SCALE),
        };

        // Then convert to repayment period rate
        return match ($repaymentSchedule) {
            'daily' => $dailyRate,
            'weekly' => bcmul($dailyRate, '7', self::RATE_SCALE),
            'bi_weekly' => bcmul($dailyRate, '14', self::RATE_SCALE),
            'monthly' => bcmul($dailyRate, '30', self::RATE_SCALE),
            default => bcmul($dailyRate, '30', self::RATE_SCALE),
        };
    }

    /**
     * Convert the loan tenure to the number of interest periods.
     */
    private function convertTenureToPeriodUnit(int $tenure, string $tenureType, string $interestPeriod, string $repaymentSchedule): string
    {
        if ($repaymentSchedule === 'bullet') {
            // For bullet, 1 period of the interest period type
            return '1';
        }

        // Convert tenure to days first
        $days = match ($tenureType) {
            'days' => (string) $tenure,
            'weeks' => bcmul((string) $tenure, '7'),
            'months' => bcmul((string) $tenure, '30'),
            default => bcmul((string) $tenure, '30'),
        };

        // Convert days to the interest period unit
        return match ($interestPeriod) {
            'daily' => $days,
            'weekly' => bcdiv($days, '7', self::RATE_SCALE),
            'monthly' => bcdiv($days, '30', self::RATE_SCALE),
            'annually' => bcdiv($days, '365', self::RATE_SCALE),
            default => bcdiv($days, '30', self::RATE_SCALE),
        };
    }

    /**
     * Add N repayment periods to the disbursement date.
     */
    private function addPeriod(Carbon $base, string $repaymentSchedule, int $n): Carbon
    {
        $clone = $base->copy();

        return match ($repaymentSchedule) {
            'daily' => $clone->addDays($n),
            'weekly' => $clone->addWeeks($n),
            'bi_weekly' => $clone->addWeeks($n * 2),
            'monthly' => $clone->addMonths($n),
            'bullet' => $clone->addMonths($n), // maturity date = 1 period from disbursement
            default => $clone->addMonths($n),
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
        $plan = new LoanPlan;
        $plan->forceFill([
            'interest_rate' => $params['interest_rate'],
            'interest_type' => $params['interest_type'],
            'interest_period' => $params['interest_period'],
            'tenure_type' => $params['tenure_type'],
            'repayment_schedule' => $params['repayment_schedule'],
            'processing_fee' => $params['processing_fee'] ?? 0,
            'insurance_fee' => $params['insurance_fee'] ?? 0,
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
        $schedStr = $this->sched($plan->repayment_schedule);

        if ($schedStr === 'bullet') {
            // Tenure in tenure_type units
            return match ($plan->tenure_type) {
                'days' => $disbursed->addDays($tenure)->toDateString(),
                'weeks' => $disbursed->addWeeks($tenure)->toDateString(),
                'months' => $disbursed->addMonths($tenure)->toDateString(),
                default => $disbursed->addMonths($tenure)->toDateString(),
            };
        }

        // For periodic schedules: last instalment date
        return $this->addPeriod($disbursed, $schedStr, $tenure)->toDateString();
    }
}
