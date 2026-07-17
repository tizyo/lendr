<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    protected $fillable = [
        'user_id', 'loan_type_id', 'trigger', 'calc_type',
        'rate', 'min_amount', 'max_amount', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate'       => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(StaffCommission::class, 'rule_id');
    }

    /** Calculate commission amount for a given base amount. */
    public function calculate(float $baseAmount): float
    {
        if ($this->min_amount && $baseAmount < (float) $this->min_amount) {
            return 0.0;
        }

        return $this->calc_type === 'percentage'
            ? round($baseAmount * (float) $this->rate / 100, 2)
            : (float) $this->rate;
    }
}
