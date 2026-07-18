<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'event',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Channels and Events ─────────────────────────────────────────────────

    public static function channels(): array
    {
        return ['in_app', 'email', 'sms'];
    }

    public static function events(): array
    {
        return [
            'loan_status_change',
            'payment_recorded',
            'overdue_reminder',
            'payment_reminder',
            'broadcast',
            'expense_approval',
        ];
    }

    /**
     * Returns the full defaults matrix for a user (all channel × event combos enabled).
     */
    public static function defaultMatrix(): array
    {
        $matrix = [];
        foreach (self::channels() as $channel) {
            foreach (self::events() as $event) {
                $matrix[] = ['channel' => $channel, 'event' => $event, 'is_enabled' => true];
            }
        }

        return $matrix;
    }

    /**
     * Get or build the preference matrix for a given user.
     * Merges DB rows with defaults so every channel×event combo is always present.
     */
    public static function matrixForUser(int $userId): array
    {
        $stored = self::where('user_id', $userId)
            ->get()
            ->keyBy(fn ($p) => "{$p->channel}.{$p->event}");

        $result = [];
        foreach (self::channels() as $channel) {
            $result[$channel] = [];
            foreach (self::events() as $event) {
                $key = "{$channel}.{$event}";
                $result[$channel][$event] = $stored->has($key)
                    ? $stored[$key]->is_enabled
                    : true; // default: enabled
            }
        }

        return $result;
    }
}
