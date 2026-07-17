<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    protected $fillable = [
        'name',
        'min_points',
        'fee_discount_pct',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_points'       => 'integer',
            'fee_discount_pct' => 'float',
            'is_active'        => 'boolean',
        ];
    }

    /**
     * Resolve the tier name for a given points balance.
     */
    public static function resolveFor(int $points): string
    {
        $tier = static::where('is_active', true)
            ->where('min_points', '<=', $points)
            ->orderByDesc('min_points')
            ->first();

        return $tier?->name ?? 'Bronze';
    }

    /**
     * Get the processing fee discount % for a given tier name.
     */
    public static function discountFor(string $tierName): float
    {
        $tier = static::where('name', $tierName)->where('is_active', true)->first();
        return $tier ? (float) $tier->fee_discount_pct : 0.0;
    }
}
