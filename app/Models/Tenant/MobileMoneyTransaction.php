<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MobileMoneyTransaction extends Model
{
    protected $fillable = [
        'provider',
        'transaction_id',
        'internal_ref',
        'transactable_type',
        'transactable_id',
        'phone',
        'amount',
        'currency',
        'direction',
        'status',
        'provider_response',
        'failure_reason',
        'retry_count',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'retry_count'  => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function transactable(): MorphTo
    {
        return $this->morphTo();
    }
}
