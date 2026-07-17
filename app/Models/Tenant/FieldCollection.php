<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldCollection extends Model
{
    protected $fillable = [
        'user_id',
        'loan_id',
        'borrower_id',
        'amount',
        'collection_method',
        'reference_number',
        'latitude',
        'longitude',
        'receipt_number',
        'notes',
        'payment_id',
        'collected_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'float',
            'latitude'     => 'float',
            'longitude'    => 'float',
            'collected_at' => 'datetime',
            'synced_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
