<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceInterest extends Model
{
    protected $fillable = [
        'listing_id',
        'user_id',
        'amount_offered',
        'interest_rate',
        'message',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_offered' => 'decimal:2',
            'interest_rate'  => 'decimal:2',
            'responded_at'   => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
