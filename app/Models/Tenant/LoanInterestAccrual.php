<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInterestAccrual extends Model
{
    protected $fillable = [
        'loan_id',
        'accrual_date',
        'principal_outstanding',
        'daily_rate',
        'accrued_amount',
        'status',
        'is_suspended',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'principal_outstanding' => 'decimal:2',
            'daily_rate' => 'decimal:6',
            'accrued_amount' => 'decimal:2',
            'is_suspended' => 'boolean',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
