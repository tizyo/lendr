<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlatformSmsConfig extends Model
{
    protected $fillable = [
        'provider',
        'is_active',
        'api_key',
        'username',
        'sender_id',
        'sandbox',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sandbox'   => 'boolean',
        'api_key'   => 'encrypted',
    ];

    protected $hidden = ['api_key'];

    // ─── Finders ──────────────────────────────────────────────────────────────

    public static function active(): ?self
    {
        if (! Schema::hasTable('platform_sms_configs')) return null;
        return static::where('is_active', true)->first();
    }

    public static function allKeyed(): array
    {
        if (! Schema::hasTable('platform_sms_configs')) return [];
        return static::all()->keyBy('provider')->all();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return ! empty($this->api_key);
    }

    /** Activate this provider and deactivate all others. */
    public function activate(): void
    {
        static::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
