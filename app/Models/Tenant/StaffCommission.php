<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCommission extends Model
{
    protected $fillable = [
        'user_id', 'loan_id', 'rule_id', 'trigger',
        'base_amount', 'commission_amount', 'status',
        'period_month', 'approved_at', 'approved_by',
        'paid_at', 'paid_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_amount'       => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'period_month'      => 'date',
            'approved_at'       => 'datetime',
            'paid_at'           => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
