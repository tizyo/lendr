<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    protected $fillable = [
        'filename', 'bank_name', 'statement_from', 'statement_to',
        'total_rows', 'matched_count', 'unmatched_count', 'status', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_from' => 'date',
            'statement_to'   => 'date',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
