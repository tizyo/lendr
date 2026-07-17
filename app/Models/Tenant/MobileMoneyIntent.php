<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyIntent extends Model
{
    protected $fillable = [
        'loan_id',
        'borrower_id',
        'reference',
        'provider',
        'phone',
        'amount',
        'currency',
        'status',
        'provider_transaction_id',
        'provider_response',
        'payment_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
