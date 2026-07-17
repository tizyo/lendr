<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPoint extends Model
{
    protected $table = 'loyalty_points';

    protected $fillable = [
        'borrower_id',
        'points',
        'type',
        'description',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
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
