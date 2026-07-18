<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GlJournalEntry extends Model
{
    protected $fillable = [
        'reference',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(GlJournalLine::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Reference Generator ──────────────────────────────────────────────────

    public static function nextReference(): string
    {
        $prefix = 'JNL-'.now()->format('Y').'-';
        $last = self::where('reference', 'like', $prefix.'%')->max('reference');
        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ─── Double-Entry Validation ──────────────────────────────────────────────

    public function isBalanced(): bool
    {
        $debits = (float) $this->lines()->where('side', 'debit')->sum('amount');
        $credits = (float) $this->lines()->where('side', 'credit')->sum('amount');

        return abs($debits - $credits) < 0.01;
    }
}
