<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPolicy extends Model
{
    protected $fillable = [
        'name',
        'rule_type',
        'operator',
        'value',
        'action',
        'is_active',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function flags(): HasMany
    {
        return $this->hasMany(RiskFlag::class);
    }

    // ─── Enum helpers ─────────────────────────────────────────────────────────

    public static function ruleTypes(): array
    {
        return [
            'max_active_loans',
            'min_credit_score',
            'max_loan_to_income',
            'blacklisted_region',
            'blacklisted_employer',
            'max_loan_amount',
            'min_borrower_age',
        ];
    }

    // ─── Evaluation ──────────────────────────────────────────────────────────

    /**
     * Evaluate this policy against a loan application context.
     * Returns null if the policy does not apply or passes; otherwise returns the failure detail.
     */
    public function evaluate(array $context): ?string
    {
        if (! $this->is_active) {
            return null;
        }

        return match ($this->rule_type) {
            'max_active_loans' => $this->checkMaxActiveLoans($context),
            'min_credit_score' => $this->checkMinCreditScore($context),
            'max_loan_to_income' => $this->checkLoanToIncome($context),
            'blacklisted_region' => $this->checkBlacklist($context, 'city'),
            'blacklisted_employer' => $this->checkBlacklist($context, 'employer'),
            'max_loan_amount' => $this->checkMaxLoanAmount($context),
            'min_borrower_age' => $this->checkMinAge($context),
            default => null,
        };
    }

    private function checkMaxActiveLoans(array $ctx): ?string
    {
        $max = (int) $this->value;
        $active = Loan::where('borrower_id', $ctx['borrower_id'] ?? 0)
            ->whereIn('status', ['disbursed', 'active'])
            ->count();

        return $active >= $max
            ? "Borrower has {$active} active loan(s); maximum allowed is {$max}."
            : null;
    }

    private function checkMinCreditScore(array $ctx): ?string
    {
        $min = (int) $this->value;
        $score = (int) ($ctx['credit_score'] ?? 0);

        return $score < $min
            ? "Borrower credit score {$score} is below the minimum required {$min}."
            : null;
    }

    private function checkLoanToIncome(array $ctx): ?string
    {
        $maxRatio = (float) $this->value;
        $amount = (float) ($ctx['requested_amount'] ?? 0);
        $income = (float) ($ctx['monthly_income'] ?? 0);

        if ($income <= 0) {
            return null;
        }

        $ratio = $amount / $income;

        return $ratio > $maxRatio
            ? sprintf('Loan-to-income ratio %.2f exceeds maximum allowed %.2f.', $ratio, $maxRatio)
            : null;
    }

    private function checkBlacklist(array $ctx, string $field): ?string
    {
        $list = json_decode($this->value, true) ?? [$this->value];
        $value = strtolower(trim($ctx[$field] ?? ''));

        if ($value === '') {
            return null;
        }

        $hit = collect($list)->first(fn ($item) => strtolower(trim($item)) === $value);

        return $hit
            ? ucfirst(str_replace('_', ' ', $field))." '{$value}' is on the exclusion list."
            : null;
    }

    private function checkMaxLoanAmount(array $ctx): ?string
    {
        $max = (float) $this->value;
        $amount = (float) ($ctx['requested_amount'] ?? 0);

        return $amount > $max
            ? 'Requested amount '.number_format($amount, 2).' exceeds maximum allowed '.number_format($max, 2).'.'
            : null;
    }

    private function checkMinAge(array $ctx): ?string
    {
        $minAge = (int) $this->value;
        $dob = $ctx['date_of_birth'] ?? null;

        if (! $dob) {
            return null;
        }

        $age = now()->diffInYears(\Carbon\Carbon::parse($dob));

        return $age < $minAge
            ? "Borrower age {$age} is below the minimum required age of {$minAge}."
            : null;
    }
}
