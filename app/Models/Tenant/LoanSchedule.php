<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSchedule extends Model
{
    protected $fillable = [
        'loan_id',
        'instalment_number',
        'due_date',
        'principal_due',
        'interest_due',
        'fee_due',
        'total_due',
        'principal_paid',
        'interest_paid',
        'fee_paid',
        'penalty_paid',
        'total_paid',
        'outstanding',
        'is_paid',
        'paid_date',
        'days_overdue',
        'penalty_accrued',
    ];

    protected function casts(): array
    {
        return [
            'due_date'  => 'date',
            'paid_date' => 'date',
            'is_paid'   => 'boolean',
            'principal_due'  => 'decimal:2',
            'interest_due'   => 'decimal:2',
            'fee_due'        => 'decimal:2',
            'total_due'      => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid'  => 'decimal:2',
            'fee_paid'       => 'decimal:2',
            'penalty_paid'   => 'decimal:2',
            'total_paid'     => 'decimal:2',
            'outstanding'    => 'decimal:2',
            'penalty_accrued' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->is_paid && $this->due_date->isPast();
    }

    public function getDaysOverdueAttribute(): int
    {
        if ($this->is_paid || ! $this->due_date->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }
}
