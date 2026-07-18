<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProvision extends Model
{
    protected $fillable = [
        'loan_id',
        'recorded_by',
        'stage',
        'stage_label',
        'days_past_due',
        'outstanding_balance',
        'provision_rate',
        'provision_amount',
        'calculation_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'days_past_due' => 'integer',
            'outstanding_balance' => 'decimal:2',
            'provision_rate' => 'decimal:4',
            'provision_amount' => 'decimal:2',
            'calculation_date' => 'date',
        ];
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
