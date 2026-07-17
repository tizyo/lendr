<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrbIdentity extends Model
{
    protected $table = 'crb_identities';

    protected $fillable = [
        'identity_hash', 'identity_type', 'credit_score', 'score_band',
        'total_loans_taken', 'total_loans_completed', 'total_loans_defaulted',
        'total_loans_written_off', 'active_loan_count',
        'total_amount_borrowed', 'total_amount_repaid',
        'first_loan_date', 'last_score_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'first_loan_date'      => 'date',
            'last_score_updated_at' => 'datetime',
            'credit_score'         => 'integer',
            'active_loan_count'    => 'integer',
            'total_loans_taken'    => 'integer',
            'total_loans_completed' => 'integer',
            'total_loans_defaulted' => 'integer',
            'total_loans_written_off' => 'integer',
        ];
    }

    public function scoreEvents(): HasMany
    {
        return $this->hasMany(CrbScoreEvent::class, 'identity_hash', 'identity_hash');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(CrbInquiry::class, 'identity_hash', 'identity_hash');
    }

    /** Human-readable risk level label. */
    public function getRiskLevelAttribute(): string
    {
        return $this->score_band;
    }
}
