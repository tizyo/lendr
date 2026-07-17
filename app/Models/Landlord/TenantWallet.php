<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores per-Enterprise-tenant payment gateway wallet credentials.
 * Configured by the landlord/superadmin; used by AutoDisburseLoanJob and ProcessAutoDebitJob.
 *
 * Sensitive fields (api_key, api_secret, webhook_secret) are encrypted at rest.
 */
class TenantWallet extends Model
{
    protected $fillable = [
        'tenant_id',
        'gateway',
        'environment',
        'wallet_id',
        'api_key',
        'api_secret',
        'webhook_secret',
        'metadata',
        'disburse_enabled',
        'debit_enabled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata'         => 'array',
            'disburse_enabled' => 'boolean',
            'debit_enabled'    => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    // ─── Encrypted field accessors / mutators ────────────────────────────────

    public function getApiKeyAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try { return decrypt($value); } catch (\Throwable) { return $value; }
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? encrypt($value) : null;
    }

    public function getApiSecretAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try { return decrypt($value); } catch (\Throwable) { return $value; }
    }

    public function setApiSecretAttribute(?string $value): void
    {
        $this->attributes['api_secret'] = $value ? encrypt($value) : null;
    }

    public function getWebhookSecretAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try { return decrypt($value); } catch (\Throwable) { return $value; }
    }

    public function setWebhookSecretAttribute(?string $value): void
    {
        $this->attributes['webhook_secret'] = $value ? encrypt($value) : null;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Return metadata key with fallback. */
    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /** Find active wallet for a given tenant ID. */
    public static function forTenant(string $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->where('is_active', true)->first();
    }
}
