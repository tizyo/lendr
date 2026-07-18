<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskFlag extends Model
{
    protected $fillable = [
        'loan_id',
        'risk_policy_id',
        'severity',
        'detail',
        'overridden',
        'overridden_by',
        'override_reason',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'overridden' => 'boolean',
            'overridden_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RiskPolicy::class, 'risk_policy_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
