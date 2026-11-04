<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = [
        'name',
        'client_key', // a short preview only — the key itself is never stored
        'key_hash',
        'client_secret',
        'scopes',
        'is_active',
        'rate_limit_per_minute',
        'created_by',
        'last_used_at',
    ];

    protected $hidden = ['client_secret', 'key_hash'];

    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Give this client a new key: store its hash (and a preview), register it in
     * the central index so the gateway can find this workspace, and return the
     * plain key — the only time it is ever available.
     */
    public function issueKey(): string
    {
        $raw = self::generateKey();
        $previous = $this->key_hash;

        $this->forceFill([
            'client_key' => substr($raw, 0, 12).'…',
            'key_hash' => self::hashKey($raw),
            'client_secret' => \Illuminate\Support\Facades\Hash::make($raw),
        ])->save();

        if ($tenantId = tenant('id')) {
            if ($previous) {
                \App\Models\Landlord\ApiKeyIndex::where('key_hash', $previous)->delete();
            }
            \App\Models\Landlord\ApiKeyIndex::updateOrCreate(
                ['key_hash' => $this->key_hash],
                ['tenant_id' => $tenantId, 'api_client_id' => $this->id],
            );
        }

        return $raw;
    }

    protected static function booted(): void
    {
        static::deleted(function (ApiClient $client) {
            if ($client->key_hash) {
                \App\Models\Landlord\ApiKeyIndex::where('key_hash', $client->key_hash)->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ApiAccessLog::class)->orderByDesc('created_at');
    }

    public static function generateKey(): string
    {
        return 'lndr_'.Str::random(40);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }
}
