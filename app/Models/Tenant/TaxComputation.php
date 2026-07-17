<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxComputation extends Model
{
    protected $fillable = [
        'tax_configuration_id',
        'source_type',
        'source_id',
        'taxable_amount',
        'tax_amount',
        'period',
        'status',
        'remitted_at',
    ];

    protected function casts(): array
    {
        return [
            'taxable_amount' => 'float',
            'tax_amount'     => 'float',
            'remitted_at'    => 'datetime',
        ];
    }

    public function taxConfiguration(): BelongsTo
    {
        return $this->belongsTo(TaxConfiguration::class);
    }
}
