<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotDeal extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'title',
        'description',
        'loan_product',
        'interest_rate',
        'min_amount',
        'max_amount',
        'tenure',
        'requirements',
        'contact_phone',
        'contact_email',
        'badge_label',
        'image_url',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'interest_rate' => 'float',
        'min_amount' => 'float',
        'max_amount' => 'float',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────
    public function leads(): HasMany
    {
        return $this->hasMany(HotDealLead::class, 'hot_deal_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
