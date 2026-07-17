<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlJournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'side',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(GlJournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_id');
    }
}
