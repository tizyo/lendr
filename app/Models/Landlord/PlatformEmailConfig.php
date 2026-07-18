<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlatformEmailConfig extends Model
{
    protected $fillable = [
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'password' => 'encrypted',
    ];

    protected $hidden = ['password'];

    // ─── Finders ──────────────────────────────────────────────────────────────

    public static function active(): ?self
    {
        if (! Schema::hasTable('platform_email_configs')) {
            return null;
        }

        return static::where('is_active', true)->first();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return ! empty($this->host) && ! empty($this->username);
    }

    /** Build a Laravel mailer config array from this record. */
    public function toMailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption ?: null,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
