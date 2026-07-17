<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    protected $fillable = [
        'agent_id',
        'loan_id',
        'disbursed_amount',
        'commission_amount',
        'status',
        'approved_by',
        'paid_by',
        'paid_date',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'disbursed_amount'  => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'paid_date'         => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public static function statuses(): array
    {
        return ['pending', 'approved', 'paid', 'reversed'];
    }
}
