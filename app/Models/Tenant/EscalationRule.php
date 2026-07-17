<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalationRule extends Model
{
    protected $fillable = [
        'name', 'dpd_threshold', 'action', 'assigned_to', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'dpd_threshold' => 'integer', 'sort_order' => 'integer'];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function collectionCases(): HasMany
    {
        return $this->hasMany(CollectionCase::class);
    }
}
