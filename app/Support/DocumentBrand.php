<?php

namespace App\Support;

use App\Services\TenantLogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Who a generated document (PDF) comes from: the lender's own name, contact and
 * registration details, logo and colour (Settings → General / Branding), or
 * LENDR's when no workspace is active. Used by resources/views/pdf/layout.
 *
 * The logo is returned as a data URI: dompdf doesn't fetch remote images, and the
 * tenant's disk has no public URL.
 */
class DocumentBrand
{
    private const LENDR_COLOR = '#2563EB';

    private const KEYS = [
        'company_name', 'company_address', 'company_phone', 'company_email', 'company_website',
        'company_registration_number', 'company_tpin', 'company_licence_number',
        'document_footer', 'primary_color', 'currency',
    ];

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;
        $s = $tenant
            ? rescue(fn () => DB::table('settings')->whereIn('key', self::KEYS)->pluck('value', 'key'), collect(), false)
            : collect();

        $value = fn (string $key) => ($v = trim((string) $s->get($key))) !== '' ? $v : null;

        $name = $value('company_name');
        $name = $name && $name !== 'LENDR' ? $name : ($tenant?->name ?? 'LENDR');
        $color = self::hex($s->get('primary_color')) ?? self::LENDR_COLOR;

        return [
            'name' => $name,
            'is_tenant' => $tenant !== null,
            'logo' => $tenant ? self::tenantLogo() : self::file(public_path('brand/lendr-logo.png')),
            'address' => $value('company_address'),
            'phone' => $value('company_phone'),
            'email' => $value('company_email'),
            'website' => $value('company_website'),
            // Registration details a lender's documents should carry (all optional).
            'registration' => array_filter([
                'PACRA Reg. No.' => $value('company_registration_number'),
                'TPIN' => $value('company_tpin'),
                'Licence No.' => $value('company_licence_number'),
            ]),
            'footer' => $value('document_footer'),
            'currency' => $value('currency') ?? $tenant?->currency ?? 'ZMW',
            'color' => $color,
            'on_color' => self::luminance($color) > 0.55 ? '#111827' : '#FFFFFF',
            'tint' => self::mix($color, '#FFFFFF', 0.07),
            'border' => self::mix($color, '#FFFFFF', 0.25),
        ];
    }

    private static function tenantLogo(): ?string
    {
        $path = TenantLogo::path();

        if (! $path || str_ends_with(strtolower($path), '.svg')) {
            return null;
        }

        return rescue(function () use ($path) {
            $disk = Storage::disk(TenantLogo::DISK);

            return $disk->exists($path) ? self::dataUri($disk->get($path), $path) : null;
        }, null, false);
    }

    private static function file(string $path): ?string
    {
        return is_file($path) ? self::dataUri(file_get_contents($path), $path) : null;
    }

    private static function dataUri(string $bytes, string $path): string
    {
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        return "data:{$mime};base64,".base64_encode($bytes);
    }

    // ── Colour helpers (shared with ErrorPage) ────────────────────────────────────

    public static function hex(?string $value): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? strtoupper($value) : null;
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function mix(string $hex, string $with, float $weight): string
    {
        $a = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $b = array_map('hexdec', str_split(ltrim($with, '#'), 2));

        return sprintf('#%02X%02X%02X', ...array_map(
            fn ($x, $y) => (int) round($x * $weight + $y * (1 - $weight)), $a, $b,
        ));
    }
}
