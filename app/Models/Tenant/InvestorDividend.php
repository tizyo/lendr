<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorDividend extends Model
{
    protected $fillable = [
        'investor_id', 'allocation_id', 'period', 'principal',
        'return_rate', 'gross_dividend', 'tax_withheld', 'net_dividend',
        'status', 'paid_date', 'processed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'return_rate' => 'decimal:4',
            'gross_dividend' => 'decimal:2',
            'tax_withheld' => 'decimal:2',
            'net_dividend' => 'decimal:2',
            'paid_date' => 'date',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(InvestorAllocation::class);
    }
}
