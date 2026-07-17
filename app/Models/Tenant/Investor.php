<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'investor_number',
        'name',
        'email',
        'phone',
        'type',
        'national_id',
        'address',
        'country',
        'status',
        'notes',
    ];

    public function dividends(): HasMany
    {
        return $this->hasMany(InvestorDividend::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvestorAllocation::class);
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(InvestorAllocation::class)->where('status', 'active');
    }

    public static function generateInvestorNumber(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->value('investor_number');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'INV-'.str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Total capital deployed across all allocations.
     */
    public function getTotalAllocatedAttribute(): float
    {
        return (float) $this->allocations()->sum('allocated_amount');
    }

    /**
     * Total realised returns across all allocations.
     */
    public function getTotalReturnsAttribute(): float
    {
        return (float) $this->allocations()->sum('actual_return');
    }
}
