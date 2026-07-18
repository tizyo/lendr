<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AutoDecisionRule extends Model
{
    protected $fillable = [
        'name', 'product_type', 'min_credit_score', 'max_dti_pct',
        'min_income', 'max_loan_amount', 'min_tenure_months',
        'max_tenure_months', 'action', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_credit_score' => 'decimal:2',
            'max_dti_pct' => 'decimal:2',
            'min_income' => 'decimal:2',
            'max_loan_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
