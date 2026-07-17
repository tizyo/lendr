<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guarantor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loan_id',
        'name',
        'national_id',
        'phone',
        'email',
        'address',
        'relationship',
        'employer',
        'monthly_income',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'status'         => 'string',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function statusBadge(): array
    {
        return match ($this->status) {
            'approved' => ['label' => 'Approved', 'color' => 'emerald'],
            'rejected' => ['label' => 'Rejected', 'color' => 'red'],
            default    => ['label' => 'Pending',  'color' => 'amber'],
        };
    }
}
