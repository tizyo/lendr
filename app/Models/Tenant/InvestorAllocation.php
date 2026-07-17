<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorAllocation extends Model
{
    protected $fillable = [
        'investor_id',
        'loan_id',
        'recorded_by',
        'allocated_amount',
        'expected_return',
        'actual_return',
        'status',
        'allocation_date',
        'settled_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'expected_return'  => 'decimal:2',
            'actual_return'    => 'decimal:2',
            'allocation_date'  => 'date',
            'settled_date'     => 'date',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
