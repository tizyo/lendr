<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanTopup extends Model
{
    protected $fillable = [
        'loan_id',
        'requested_by',
        'approved_by',
        'topup_amount',
        'new_tenure',
        'status',
        'rejection_reason',
        'notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'topup_amount' => 'decimal:2',
            'approved_at'  => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
