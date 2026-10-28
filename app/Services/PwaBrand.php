<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The installed-app identity of the borrower PWA for the current tenant
 * (Settings → Branding → PWA App Name / PWA Theme Colour), used by the web
 * app manifest, the generated home-screen icons and the PWA page shell.
 */
class PwaBrand
{
    private const DEFAULT_COLOR = '#10B981';

    public static function current(): array
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        $s = rescue(fn () => DB::table('settings')
            ->whereIn('key', ['company_name', 'pwa_app_name', 'pwa_theme_color', 'primary_color', TenantLogo::SETTING])
            ->pluck('value', 'key'), collect(), false);

        $company = self::meaningful($s->get('company_name')) ?? $tenant?->name ?? 'LENDR';
        $name = self::meaningful($s->get('pwa_app_name')) ?? $company;
        $color = self::hex($s->get('pwa_theme_color')) ?? self::hex($s->get('primary_color')) ?? self::DEFAULT_COLOR;
        $logo = $s->get(TenantLogo::SETTING);

        return [
            'name' => $name,
            'short_name' => mb_strimwidth($name, 0, 12, ''),
            'company' => $company,
            'theme_color' => $color,
            'logo_path' => $logo && ! str_ends_with(strtolower($logo), '.svg') ? $logo : null,
            // Changes whenever anything shown on the home screen changes — busts icon caches.
            'version' => substr(md5($name.$color.$logo), 0, 8),
        ];
    }

    /** The settings defaults are literally "LENDR" — treat those as unset so the tenant's own name wins. */
    private static function meaningful(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === 'LENDR' ? null : $value;
    }

    private static function hex(?string $value): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? strtoupper($value) : null;
    }
}
