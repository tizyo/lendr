<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxConfiguration extends Model
{
    protected $fillable = [
        'tax_type',
        'rate',
        'label',
        'applies_to_interest',
        'applies_to_fees',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'applies_to_interest' => 'boolean',
            'applies_to_fees' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function computations(): HasMany
    {
        return $this->hasMany(TaxComputation::class);
    }

    public static function activeWht(): ?self
    {
        return static::where('tax_type', 'wht')->where('is_active', true)->first();
    }

    public static function activeVat(): ?self
    {
        return static::where('tax_type', 'vat')->where('is_active', true)->first();
    }
}
