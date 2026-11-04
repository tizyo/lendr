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

    /**
     * Why a staff-entered application doesn't fit this plan, keyed by form field
     * (empty when it fits). The borrower app always checked these; the staff
     * screens and API accepted any amount, any tenure, and a plan of another type.
     *
     * @return array<string, string>
     */
    public function violations(float $amount, int $tenure, ?int $loanTypeId = null): array
    {
        $errors = [];

        if ($loanTypeId !== null && (int) $this->loan_type_id !== $loanTypeId) {
            $errors['loan_plan_id'] = 'This plan belongs to a different loan type.';
        } elseif (! $this->is_active) {
            $errors['loan_plan_id'] = 'This plan is switched off.';
        }

        $min = $this->min_amount !== null ? (float) $this->min_amount : null;
        $max = $this->max_amount !== null ? (float) $this->max_amount : null;
        if (($min !== null && $amount < $min) || ($max !== null && $max > 0 && $amount > $max)) {
            $errors['principal_amount'] = 'The amount must be between '.number_format((float) $min, 2).' and '.number_format((float) $max, 2).' on this plan.';
        }

        if (($this->min_tenure && $tenure < $this->min_tenure) || ($this->max_tenure && $tenure > $this->max_tenure)) {
            $errors['tenure'] = "The tenure must be between {$this->min_tenure} and {$this->max_tenure} {$this->tenure_type} on this plan.";
        }

        return $errors;
    }
}
