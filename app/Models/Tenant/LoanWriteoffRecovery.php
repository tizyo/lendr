<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanWriteoffRecovery extends Model
{
    protected $fillable = [
        'loan_writeoff_id',
        'recorded_by',
        'amount',
        'method',
        'reference',
        'notes',
        'recovery_date',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'recovery_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function writeoff(): BelongsTo
    {
        return $this->belongsTo(LoanWriteoff::class, 'loan_writeoff_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
