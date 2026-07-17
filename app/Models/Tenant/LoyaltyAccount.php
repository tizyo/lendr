<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    protected $fillable = [
        'borrower_id',
        'total_points',
        'tier',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class, 'borrower_id', 'borrower_id');
    }
}
