<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseApprovalSetting extends Model
{
    protected $fillable = [
        'expense_category_id',
        'threshold_amount',
        'approver_role',
        'requires_receipt',
    ];

    protected function casts(): array
    {
        return [
            'threshold_amount' => 'decimal:2',
            'requires_receipt' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
