<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementLog extends Model
{
    protected $fillable = [
        'loan_id',
        'gateway',
        'reference',
        'provider_reference',
        'amount',
        'recipient_phone',
        'status',
        'provider_response',
        'failure_reason',
        'used_wallet',
    ];

    protected function casts(): array
    {
        return [
            'amount'            => 'decimal:2',
            'provider_response' => 'array',
            'used_wallet'       => 'boolean',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
