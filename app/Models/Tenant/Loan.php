<?php

namespace App\Models\Tenant;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Jobs\RecalculateCreditScoreJob;
use Database\Factories\Tenant\LoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Loan extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        static::updated(function (Loan $loan) {
            if ($loan->wasChanged('status') && in_array($loan->status->value, ['completed', 'defaulted', 'written_off'])) {
                dispatch(new RecalculateCreditScoreJob($loan->borrower_id));
            }
        });
    }

    protected static function newFactory(): LoanFactory
    {
        return LoanFactory::new();
    }

    protected $fillable = [
        'loan_number',
        'borrower_id',
        'loan_type_id',
        'loan_plan_id',
        'created_by',
        'approved_by',
        'disbursed_by',
        'branch_id',
        'principal_amount',
        'interest_amount',
        'processing_fee',
        'insurance_fee',
        'total_payable',
        'total_paid',
        'outstanding_balance',
        'penalty_balance',
        'interest_rate',
        'interest_type',
        'interest_period',
        'tenure',
        'tenure_type',
        'repayment_schedule',
        'penalty_rate',
        'grace_period_days',
        'disbursement_method',
        'disbursement_account',
        'disbursement_reference',
        'currency',
        'fx_rate',
        'base_currency',
        'status',
        'application_date',
        'approval_date',
        'disbursement_date',
        'first_repayment_date',
        'maturity_date',
        'closed_date',
        'collateral_description',
        'guarantor_name',
        'guarantor_phone',
        'guarantor_relationship',
        'loan_purpose',
        'momo_transaction_id',
        'momo_provider',
        'legacy_loan_id',
        'migration_source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'disbursement_method' => PaymentMethod::class,
            'fx_rate' => 'decimal:6',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'penalty_balance' => 'decimal:2',
            'application_date' => 'date',
            'approval_date' => 'date',
            'disbursement_date' => 'date',
            'first_repayment_date' => 'date',
            'maturity_date' => 'date',
            'closed_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'outstanding_balance', 'total_paid'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ─────────────────────────────────

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(LoanType::class);
    }

    public function loanPlan(): BelongsTo
    {
        return $this->belongsTo(LoanPlan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function schedule(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('instalment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('payment_date');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(Guarantor::class);
    }

    public function collateralItems(): HasMany
    {
        return $this->hasMany(CollateralItem::class);
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(LoanInsurance::class);
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(LoanProvision::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(LoanPenalty::class)->orderByDesc('penalty_date');
    }

    public function interestAccruals(): HasMany
    {
        return $this->hasMany(LoanInterestAccrual::class)->orderByDesc('accrual_date');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(LoanStatusLog::class)->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LoanDocument::class);
    }

    public function collectionLogs(): HasMany
    {
        return $this->hasMany(CollectionLog::class)->orderByDesc('created_at');
    }

    public function topups(): HasMany
    {
        return $this->hasMany(LoanTopup::class)->orderByDesc('created_at');
    }

    public function disbursementLogs(): HasMany
    {
        return $this->hasMany(DisbursementLog::class)->orderByDesc('created_at');
    }

    public function standingOrders(): HasMany
    {
        return $this->hasMany(StandingOrder::class)->orderBy('due_date');
    }

    // ─── Scopes ────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', [LoanStatus::Disbursed->value, LoanStatus::Active->value]);
    }

    public function scopeOverdue($query)
    {
        return $query->active()->whereHas('schedule', function ($q) {
            $q->where('is_paid', false)->where('due_date', '<', now()->toDateString());
        });
    }

    // ─── Helpers ───────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isOverdue(): bool
    {
        return $this->schedule()
            ->where('is_paid', false)
            ->where('due_date', '<', now()->toDateString())
            ->exists();
    }

    public function getDaysOverdueAttribute(): int
    {
        $earliest = $this->schedule()
            ->where('is_paid', false)
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->first();

        return $earliest
            ? now()->diffInDays($earliest->due_date)
            : 0;
    }
}
