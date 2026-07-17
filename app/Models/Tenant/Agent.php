<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'national_id',
        'address',
        'commission_rate',
        'commission_type',
        'fixed_commission',
        'status',
        'managed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate'   => 'decimal:2',
            'fixed_commission'  => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function managedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public static function generateAgentNumber(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->value('agent_number');
        $next = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'AGT'.str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function calculateCommission(float $disbursedAmount): float
    {
        if ($this->commission_type === 'fixed') {
            return (float) $this->fixed_commission;
        }

        return round($disbursedAmount * ((float) $this->commission_rate / 100), 2);
    }

    public static function statuses(): array
    {
        return ['active', 'suspended', 'terminated'];
    }
}
