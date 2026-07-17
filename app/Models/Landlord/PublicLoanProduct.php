<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class PublicLoanProduct extends Model
{
    protected $table = 'public_loan_products';

    protected $fillable = [
        'tenant_id', 'tenant_name', 'tenant_city',
        'product_name', 'product_code', 'description',
        'min_amount', 'max_amount', 'interest_rate',
        'interest_type', 'interest_period',
        'min_tenure', 'max_tenure', 'tenure_type',
        'repayment_schedule', 'processing_fee',
        'requires_collateral', 'requires_guarantor',
        'is_active', 'applications_count',
    ];

    protected function casts(): array
    {
        return [
            'min_amount'          => 'decimal:2',
            'max_amount'          => 'decimal:2',
            'interest_rate'       => 'decimal:4',
            'processing_fee'      => 'decimal:4',
            'requires_collateral' => 'boolean',
            'requires_guarantor'  => 'boolean',
            'is_active'           => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
