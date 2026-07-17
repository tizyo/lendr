<?php

namespace App\Models\Tenant;

use App\Enums\ExpenseStatus;
use Database\Factories\Tenant\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
    }

    protected $fillable = [
        'expense_number',
        'expense_category_id',
        'submitted_by',
        'approved_by',
        'title',
        'description',
        'amount',
        'currency',
        'payment_method',
        'vendor',
        'receipt_reference',
        'expense_date',
        'status',
        'rejection_reason',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => ExpenseStatus::class,
            'amount'       => 'decimal:2',
            'expense_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExpenseDocument::class);
    }
}
