<?php

namespace App\Models\Tenant;

use App\Jobs\SendBorrowerPushJob;
use App\Services\PushNotificationService;
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
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every in-app notification is also pushed to the borrower's phone (if they subscribed).
        static::created(function (self $notification) {
            if (app(PushNotificationService::class)->isConfigured()) {
                SendBorrowerPushJob::dispatch($notification)->afterCommit();
            }
        });
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
