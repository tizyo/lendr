<?php

namespace App\Models\Tenant;

use Database\Factories\Tenant\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected static function newFactory(): ExpenseCategoryFactory
    {
        return ExpenseCategoryFactory::new();
    }

    protected $fillable = [
        'name', 'code', 'icon', 'colour', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ExpenseBudget::class);
    }

    public function approvalSettings(): HasMany
    {
        return $this->hasMany(ExpenseApprovalSetting::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
