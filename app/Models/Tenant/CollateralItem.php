<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollateralItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loan_id',
        'type',
        'description',
        'estimated_value',
        'assessed_value',
        'assessment_date',
        'location',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'pending',
        'type'   => 'other',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'assessed_value'  => 'decimal:2',
        'assessment_date' => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return match ($this->type) {
            'property'  => 'Property',
            'vehicle'   => 'Vehicle',
            'equipment' => 'Equipment',
            'land'      => 'Land',
            'savings'   => 'Savings/Deposit',
            default     => 'Other',
        };
    }

    public function statusBadge(): array
    {
        return match ($this->status) {
            'verified' => ['label' => 'Verified',  'color' => 'emerald'],
            'released' => ['label' => 'Released',  'color' => 'neutral'],
            default    => ['label' => 'Pending',   'color' => 'amber'],
        };
    }
}
