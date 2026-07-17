<?php

namespace App\Models\Tenant;

use Database\Factories\Tenant\LoanTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanType extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): LoanTypeFactory
    {
        return LoanTypeFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'requires_collateral',
        'requires_guarantor',
        'required_documents',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_collateral' => 'boolean',
            'requires_guarantor'  => 'boolean',
            'required_documents'  => 'array',
            'is_active'           => 'boolean',
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(LoanPlan::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
