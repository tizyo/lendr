<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoDecision extends Model
{
    protected $fillable = [
        'loan_id', 'rule_id', 'action', 'credit_score',
        'dti_pct', 'factors', 'notes', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'factors' => 'array',
            'credit_score' => 'decimal:2',
            'dti_pct' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutoDecisionRule::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
