<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPenalty extends Model
{
    protected $fillable = [
        'loan_id',
        'schedule_id',
        'penalty_date',
        'days_overdue',
        'penalty_rate',
        'overdue_amount',
        'penalty_amount',
        'waived_amount',
        'waived_by',
        'waived_at',
        'waiver_reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'penalty_date'   => 'date',
            'waived_at'      => 'datetime',
            'penalty_rate'   => 'decimal:4',
            'overdue_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'waived_amount'  => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class);
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}
