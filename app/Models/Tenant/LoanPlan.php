<?php

namespace App\Models\Tenant;

use App\Enums\RepaymentSchedule;
use Database\Factories\Tenant\LoanPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): LoanPlanFactory
    {
        return LoanPlanFactory::new();
    }

    protected $fillable = [
        'loan_type_id',
        'name',
        'code',
        'interest_rate',
        'interest_type',
        'interest_period',
        'min_tenure',
        'max_tenure',
        'tenure_type',
        'min_amount',
        'max_amount',
        'penalty_rate',
        'penalty_type',
        'grace_period_days',
        'repayment_schedule',
        'processing_fee',
        'insurance_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:4',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'penalty_rate' => 'decimal:4',
            'processing_fee' => 'decimal:4',
            'insurance_fee' => 'decimal:4',
            'is_active' => 'boolean',
            'repayment_schedule' => RepaymentSchedule::class,
        ];
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
