<?php

namespace App\Models;

use App\Models\Landlord\RepoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedRepoItem extends Model
{
    protected $fillable = [
        'repo_item_id',
        'tenant_id',
        'type',
        'amount_paid',
        'days_paid',
        'payment_reference',
        'payment_status',
        'starts_at',
        'expires_at',
        'is_active',
        'approved_by',
        'admin_note',
    ];

    protected $casts = [
        'amount_paid' => 'float',
        'days_paid' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ─── Rate constants ────────────────────────────────────────────────────
    public const RATE_PER_DAY = 50.00;      // K50/day

    public const MAX_ACTIVE_PER_TENANT = 10;

    // ─── Relationships ─────────────────────────────────────────────────────
    public function repoItem(): BelongsTo
    {
        return $this->belongsTo(RepoItem::class, 'repo_item_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopePaid($query)
    {
        return $query->where('type', 'paid');
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function daysRemaining(): int
    {
        if ($this->expires_at === null) {
            return -1; // indefinite
        }

        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    /**
     * Calculate cost for N days at the flat rate.
     */
    public static function costForDays(int $days): float
    {
        return round($days * self::RATE_PER_DAY, 2);
    }

    /**
     * How many active paid featured slots does a tenant currently occupy?
     */
    public static function activePaidCountForTenant(string $tenantId): int
    {
        return static::active()->paid()
            ->where('tenant_id', $tenantId)
            ->count();
    }
}
