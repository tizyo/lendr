<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class FundBalance extends Model
{
    protected $table = 'fund_balance';

    protected $fillable = [
        'opening_balance',
        'total_deposits',
        'total_disbursed',
        'total_repaid',
        'total_penalties',
        'total_expenses',
        'available_balance',
        'currency',
        'last_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'total_deposits' => 'decimal:2',
            'total_disbursed' => 'decimal:2',
            'total_repaid' => 'decimal:2',
            'total_penalties' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'last_reconciled_at' => 'datetime',
        ];
    }

    /**
     * Get the single fund balance row, creating it if it doesn't exist.
     */
    public static function current(): static
    {
        return static::firstOrCreate([], [
            'opening_balance' => 0,
            'total_deposits' => 0,
            'total_disbursed' => 0,
            'total_repaid' => 0,
            'total_penalties' => 0,
            'total_expenses' => 0,
            'available_balance' => 0,
            'currency' => 'ZMW',
        ]);
    }
}
