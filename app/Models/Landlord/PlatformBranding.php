<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton platform branding config (always one row, first-or-create pattern).
 */
class PlatformBranding extends Model
{
    protected $table = 'platform_branding';

    protected $fillable = [
        'company_name',
        'tagline',
        'address',
        'phone',
        'email',
        'website',
        'logo_path',
        'favicon_path',
        'primary_color',
        'invoice_footer',
        'email_footer',
    ];

    protected $attributes = [
        'company_name' => 'LENDR',
        'primary_color' => '#059669',
    ];

    // ─── Static helpers ───────────────────────────────────────────────────────

    /**
     * Returns the singleton row, creating it if absent. Returns null if the
     * table has not been migrated yet (avoids boot-time errors in tests).
     */
    public static function current(): ?self
    {
        if (! Schema::hasTable('platform_branding')) {
            return null;
        }

        return static::firstOrCreate([]);
    }

    /**
     * Returns a safe array of branding values, falling back to sensible defaults
     * when the table doesn't exist yet.
     */
    public static function defaults(): array
    {
        $b = static::current();

        return [
            'company_name' => $b?->company_name ?? config('app.name', 'LENDR'),
            'tagline' => $b?->tagline ?? null,
            'address' => $b?->address ?? null,
            'phone' => $b?->phone ?? null,
            'email' => $b?->email ?? null,
            'website' => $b?->website ?? null,
            'logo_url' => $b?->logoUrl(),
            'favicon_url' => $b?->faviconUrl(),
            'primary_color' => $b?->primary_color ?? '#059669',
            'invoice_footer' => $b?->invoice_footer ?? null,
            'email_footer' => $b?->email_footer ?? null,
        ];
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path
            ? Storage::disk('public')->url($this->favicon_path)
            : null;
    }
}
