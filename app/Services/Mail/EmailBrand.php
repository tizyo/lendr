<?php

namespace App\Services\Mail;

use App\Models\Landlord\PlatformBranding;
use App\Models\Landlord\Tenant;
use App\Services\TenantLogo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the look of an outgoing email for the context it is sent in:
 *
 *   - tenant context  → the tenant's own branding (Settings → Branding):
 *                       name, logo, email header / primary colour, contacts
 *   - otherwise       → platform branding (Landlord → Platform Settings)
 *
 * Registered as a scoped singleton, so a queue worker re-reads branding for
 * every job while a single render pays for the lookup only once.
 */
class EmailBrand
{
    private const PLATFORM_DEFAULT_COLOR = '#059669';

    private const TENANT_DEFAULT_COLOR = '#0D47A1'; // matches BrandingController's default

    /** @var array<string, array> */
    private array $memo = [];

    public function current(): array
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        $key = $tenant ? 'tenant:'.$tenant->getTenantKey() : 'platform';

        return $this->memo[$key] ??= $tenant instanceof Tenant
            ? $this->forTenant($tenant)
            : $this->forPlatform();
    }

    // ─── Sources ──────────────────────────────────────────────────────────────

    private function forPlatform(): array
    {
        $b = rescue(fn () => PlatformBranding::defaults(), [], false);

        return $this->build([
            'is_tenant' => false,
            'name' => $b['company_name'] ?? 'LENDR',
            'tagline' => $b['tagline'] ?? null,
            'logo_url' => $this->emailSafeImage($b['logo_url'] ?? null),
            'color' => $b['primary_color'] ?? self::PLATFORM_DEFAULT_COLOR,
            'email' => $b['email'] ?? null,
            'phone' => $b['phone'] ?? null,
            'address' => $b['address'] ?? null,
            'website' => $b['website'] ?? null,
            'footer' => $b['email_footer'] ?? null,
        ], self::PLATFORM_DEFAULT_COLOR);
    }

    private function forTenant(Tenant $tenant): array
    {
        /** @var Collection $s */
        $s = rescue(fn () => DB::table('settings')->whereIn('key', [
            'company_name', 'primary_color', 'email_header_color', TenantLogo::SETTING,
            'support_email', 'support_phone', 'address', 'website', 'email_footer_text',
            // Settings → General saves these; the branding API saves the support_* ones.
            'company_email', 'company_phone', 'company_address',
        ])->pluck('value', 'key'), collect(), false);

        $logoPath = $s->get(TenantLogo::SETTING);

        // The generic placeholder name is never a real brand — prefer the tenant's actual name.
        $name = $s->get('company_name');
        if (! $name || $name === 'LENDR') {
            $name = $tenant->name ?: $tenant->slug;
        }

        return $this->build([
            'is_tenant' => true,
            'name' => $name,
            'tagline' => null,
            'logo_url' => $logoPath && ! $this->isSvg($logoPath) ? TenantLogo::url($logoPath) : null,
            'color' => $s->get('email_header_color') ?: $s->get('primary_color') ?: self::TENANT_DEFAULT_COLOR,
            'email' => $s->get('support_email') ?: $s->get('company_email'),
            'phone' => $s->get('support_phone') ?: $s->get('company_phone'),
            'address' => $s->get('address') ?: $s->get('company_address'),
            'website' => $s->get('website'),
            'footer' => $s->get('email_footer_text'),
        ], self::TENANT_DEFAULT_COLOR);
    }

    // ─── Palette ──────────────────────────────────────────────────────────────

    private function build(array $brand, string $defaultColor): array
    {
        $primary = $this->normalizeHex($brand['color']) ?? $defaultColor;
        $isLight = $this->luminance($primary) > 0.55;

        unset($brand['color']);

        return array_merge($brand, [
            'primary' => $primary,
            // Text on a primary-coloured button: dark on pale brands, white otherwise.
            'on_primary' => $isLight ? '#111827' : '#FFFFFF',
            // Links / accents must stay readable on white even for pale brands.
            'accent' => $isLight ? $this->mix($primary, '#000000', 0.55) : $primary,
            'tint' => $this->mix($primary, '#FFFFFF', 0.07),
            'tint_border' => $this->mix($primary, '#FFFFFF', 0.22),
            'initial' => mb_strtoupper(mb_substr(trim((string) $brand['name']) ?: 'L', 0, 1)),
        ]);
    }

    private function normalizeHex(?string $hex): ?string
    {
        $hex = ltrim(trim((string) $hex), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#'.strtoupper($hex) : null;
    }

    /** Relative luminance (WCAG), 0 = black … 1 = white. */
    private function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** Blend $weight of $a with (1 - $weight) of $b. */
    private function mix(string $a, string $b, float $weight): string
    {
        $ca = sscanf($a, '#%02x%02x%02x');
        $cb = sscanf($b, '#%02x%02x%02x');

        return sprintf('#%02X%02X%02X', ...array_map(
            fn ($x, $y) => (int) round($x * $weight + $y * (1 - $weight)),
            $ca,
            $cb,
        ));
    }

    // ─── Images ───────────────────────────────────────────────────────────────

    /** Gmail and Outlook don't render SVG, so an SVG logo falls back to the wordmark. */
    private function emailSafeImage(?string $url): ?string
    {
        return $url && ! $this->isSvg($url) ? $this->absolute($url) : null;
    }

    private function isSvg(string $path): bool
    {
        return str_ends_with(strtolower(parse_url($path, PHP_URL_PATH) ?: $path), '.svg');
    }

    /** Emails are read off-site, so every image URL must be absolute on the central domain. */
    private function absolute(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
