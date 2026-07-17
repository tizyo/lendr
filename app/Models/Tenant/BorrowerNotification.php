<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerNotification extends Model
{
    protected $fillable = [
        'borrower_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }

    public function markRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
