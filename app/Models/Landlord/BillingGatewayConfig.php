<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BillingGatewayConfig extends Model
{
    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    protected $fillable = [
        'gateway',
        'is_active',
        'public_key',
        'secret_key',
        'webhook_secret',
        'extra_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'public_key' => 'encrypted',
        'secret_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'extra_config' => 'array',
    ];

    protected $hidden = ['public_key', 'secret_key', 'webhook_secret'];

    // ─── Query helpers ────────────────────────────────────────────────────────

    public static function active(): ?self
    {
        if (! Schema::connection(config('database.central_connection'))->hasTable('billing_gateway_configs')) {
            return null;
        }

        return static::where('is_active', true)->first();
    }

    public static function forGateway(string $gateway): ?self
    {
        if (! Schema::connection(config('database.central_connection'))->hasTable('billing_gateway_configs')) {
            return null;
        }

        return static::where('gateway', $gateway)->first();
    }

    public static function allIndexed(): array
    {
        if (! Schema::connection(config('database.central_connection'))->hasTable('billing_gateway_configs')) {
            return [];
        }

        return static::all()->keyBy('gateway')->all();
    }
}
