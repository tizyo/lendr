<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'name',
        'entity_type',
        'min_amount',
        'max_amount',
        'required_roles',
        'required_approvals',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'required_roles' => 'array',
            'required_approvals' => 'integer',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'workflow_id');
    }

    /**
     * Find the best matching active workflow for an entity type and amount.
     */
    public static function findFor(string $entityType, float $amount = 0): ?self
    {
        return static::where('entity_type', $entityType)
            ->where('is_active', true)
            ->where(function ($q) use ($amount) {
                $q->whereNull('min_amount')->orWhere('min_amount', '<=', $amount);
            })
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->orderByDesc('min_amount')
            ->first();
    }
}
