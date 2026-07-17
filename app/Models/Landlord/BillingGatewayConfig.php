<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BillingGatewayConfig extends Model
{
    protected $fillable = [
        'gateway',
        'is_active',
        'public_key',
        'secret_key',
        'webhook_secret',
        'extra_config',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'public_key'     => 'encrypted',
        'secret_key'     => 'encrypted',
        'webhook_secret' => 'encrypted',
        'extra_config'   => 'array',
    ];

    protected $hidden = ['public_key', 'secret_key', 'webhook_secret'];

    // ─── Query helpers ────────────────────────────────────────────────────────

    public static function active(): ?self
    {
        if (! Schema::hasTable('billing_gateway_configs')) return null;
        return static::where('is_active', true)->first();
    }

    public static function forGateway(string $gateway): ?self
    {
        if (! Schema::hasTable('billing_gateway_configs')) return null;
        return static::where('gateway', $gateway)->first();
    }

    public static function allIndexed(): array
    {
        if (! Schema::hasTable('billing_gateway_configs')) return [];
        return static::all()->keyBy('gateway')->all();
    }
}
