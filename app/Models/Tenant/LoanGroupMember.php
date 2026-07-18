<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanGroupMember extends Model
{
    protected $fillable = [
        'loan_group_id',
        'borrower_id',
        'role',
        'is_active',
        'joined_date',
        'left_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_date' => 'date',
            'left_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function group(): BelongsTo
    {
        return $this->belongsTo(LoanGroup::class, 'loan_group_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }
}
