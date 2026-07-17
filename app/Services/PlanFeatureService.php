<?php

namespace App\Services;

use App\Models\Landlord\PlanConfig;
use Stancl\Tenancy\Database\Models\Tenant;

class PlanFeatureService
{
    private ?PlanConfig $config = null;

    public function __construct(private readonly ?string $plan = null)
    {
        if ($plan) {
            $this->config = PlanConfig::forPlan($plan);
        }
    }

    public static function forTenant(): self
    {
        $plan = tenancy()->tenant?->plan ?? 'starter';
        return new self($plan);
    }

    // ── Feature boolean check ────────────────────────────────────────────────

    /**
     * Check if a boolean feature is enabled for the current plan.
     * Falls back to false if no config found.
     */
    public function has(string $feature): bool
    {
        if (!$this->config) return false;
        return $this->config->hasFeature($feature);
    }

    // ── Numeric limit check ──────────────────────────────────────────────────

    /**
     * Check if the current count is within the plan's limit for a feature.
     * -1 = unlimited.
     */
    public function withinLimit(string $feature, int $current): bool
    {
        if (!$this->config) return false;
        return $this->config->withinLimit($feature, $current);
    }

    /**
     * Get the numeric limit value (raw).
     */
    public function limit(string $feature): int
    {
        if (!$this->config) return 0;
        return (int) ($this->config->features[$feature] ?? 0);
    }

    /**
     * Human-readable limit: "Unlimited" or the number.
     */
    public function limitLabel(string $feature): string
    {
        $limit = $this->limit($feature);
        return $limit === -1 ? 'Unlimited' : (string) $limit;
    }

    // ── Convenience shortcuts ────────────────────────────────────────────────

    public function canAddUser(int $currentCount): bool
    {
        return $this->withinLimit('max_users', $currentCount);
    }

    public function canAddBranch(int $currentCount): bool
    {
        return $this->withinLimit('max_branches', $currentCount);
    }

    public function canAddLoanProduct(int $currentCount): bool
    {
        return $this->withinLimit('max_loan_products', $currentCount);
    }

    public function canAddBorrower(int $currentCount): bool
    {
        return $this->withinLimit('max_borrowers', $currentCount);
    }
}
