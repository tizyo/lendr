<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'premium_type',
        'premium_rate',
        'coverage_type',
        'max_term_months',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'premium_rate' => 'decimal:4',
            'is_active' => 'boolean',
            'max_term_months' => 'integer',
        ];
    }

    public function loanInsurances(): HasMany
    {
        return $this->hasMany(LoanInsurance::class);
    }

    /**
     * Calculate premium for a given principal amount.
     */
    public function calculatePremium(float $principalAmount): float
    {
        if ($this->premium_type === 'flat') {
            return (float) $this->premium_rate;
        }

        return round($principalAmount * ((float) $this->premium_rate / 100), 2);
    }
}
