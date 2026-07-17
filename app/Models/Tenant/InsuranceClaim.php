<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    protected $fillable = [
        'loan_insurance_id',
        'recorded_by',
        'claim_number',
        'claim_type',
        'claim_amount',
        'approved_amount',
        'status',
        'incident_date',
        'description',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'claim_amount'    => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'incident_date'   => 'date',
            'reviewed_at'     => 'datetime',
        ];
    }

    public function loanInsurance(): BelongsTo
    {
        return $this->belongsTo(LoanInsurance::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function generateClaimNumber(): string
    {
        $last = self::orderByDesc('id')->value('claim_number');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'CLM-'.str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
