<?php

namespace App\Models\Tenant;

use App\Events\NewNotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotification extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $n) => broadcast(new NewNotificationEvent($n)));
    }

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'icon',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
