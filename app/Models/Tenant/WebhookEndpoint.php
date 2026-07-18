<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    protected $fillable = [
        'url',
        'secret',
        'events',
        'description',
        'is_active',
        'failure_count',
        'last_triggered_at',
        'last_success_at',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    // ─── Event Registration ───────────────────────────────────────────────────

    public static function subscribedTo(string $event): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->get()
            ->filter(fn ($ep) => in_array($event, $ep->events ?? []));
    }

    public static function availableEvents(): array
    {
        return [
            'loan.created',
            'loan.submitted',
            'loan.approved',
            'loan.denied',
            'loan.disbursed',
            'loan.completed',
            'loan.written_off',
            'payment.recorded',
            'borrower.created',
            'borrower.updated',
            'expense.approved',
        ];
    }

    // ─── Signature ───────────────────────────────────────────────────────────

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
