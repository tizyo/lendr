<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanWriteoff extends Model
{
    protected $fillable = [
        'loan_id',
        'written_off_by',
        'written_off_amount',
        'reason',
        'total_recovered',
    ];

    protected $appends = ['written_off_at', 'recovery_rate'];

    protected function casts(): array
    {
        return [
            'written_off_amount' => 'decimal:2',
            'total_recovered'    => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function writtenOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_off_by');
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(LoanWriteoffRecovery::class);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    public function getWrittenOffAtAttribute(): ?string
    {
        return $this->created_at?->toDateString();
    }

    public function getRecoveryRateAttribute(): float
    {
        return $this->recoveryRate();
    }

    public function netLoss(): float
    {
        return (float) $this->written_off_amount - (float) $this->total_recovered;
    }

    public function recoveryRate(): float
    {
        if ((float) $this->written_off_amount === 0.0) {
            return 0.0;
        }

        return round(((float) $this->total_recovered / (float) $this->written_off_amount) * 100, 2);
    }
}
