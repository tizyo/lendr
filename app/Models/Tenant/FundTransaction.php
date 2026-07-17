<?php

namespace App\Models\Tenant;

use App\Enums\FundTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FundTransaction extends Model
{
    protected $fillable = [
        'transaction_ref',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
        'performed_by',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type'           => FundTransactionType::class,
            'amount'         => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after'  => 'decimal:2',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
