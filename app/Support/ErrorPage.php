<?php

namespace App\Support;

use App\Services\TenantLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Branded error screens. Inside a workspace they wear the lender's colour and logo
 * (Settings → Branding); everywhere else, LENDR's. Inertia visits get the Vue
 * "Error" page instead of the raw HTML Inertia would show in a modal; the Blade
 * views in resources/views/errors cover anything that can't render Inertia.
 */
class ErrorPage
{
    /** Statuses that get a designed screen. 419 is handled separately (redirect back). */
    public const STATUSES = [403, 404, 429, 500, 503];

    private const LENDR_COLOR = '#2563EB';

    private const COPY = [
        403 => ['Access denied', "You don't have permission to view this page. If you think you should, ask an administrator to check your role."],
        404 => ['Page not found', "The page you're looking for doesn't exist or has been moved."],
        419 => ['Page expired', 'This page was open too long. Refresh and try again.'],
        429 => ['Too many requests', "You're going a little fast. Wait a moment, then try again."],
        500 => ['Something went wrong', "We hit an unexpected problem and it's been logged. Please try again in a moment."],
        503 => ['Back soon', "We're doing some quick maintenance. Please check back in a few minutes."],
    ];

    /** The exception handler's hook: swap supported error responses for the branded page. */
    public static function respond(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if ($request->expectsJson() || $request->is('api/*')) {
            return $response;
        }

        // An expired CSRF token on a form post: send them back to retry, not to a dead end.
        if ($status === 419) {
            return back()->with('error', self::COPY[419][1]);
        }

        if (! in_array($status, self::STATUSES, true) || ($status === 500 && config('app.debug'))) {
            return $response;
        }

        try {
            return Inertia::render('Error', self::props($status, $request))
                ->toResponse($request)
                ->setStatusCode($status);
        } catch (Throwable) {
            return $response; // the Blade error view still renders, branded
        }
    }

    /** @return array<string, mixed> */
    public static function props(int $status, ?Request $request = null): array
    {
        [$title, $message] = self::COPY[$status] ?? self::COPY[500];

        return [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'brand' => self::brand(),
            'homeUrl' => self::homeUrl($request ?? request()),
        ];
    }

    /** @return array{name: string, logo: string, is_tenant: bool, color: string, on_color: string, tint: string} */
    public static function brand(): array
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;
        $settings = $tenant
            ? rescue(fn () => DB::table('settings')->whereIn('key', ['company_name', 'primary_color'])->pluck('value', 'key'), collect(), false)
            : collect();

        $color = self::hex($settings->get('primary_color')) ?? self::LENDR_COLOR;
        $logo = $tenant ? rescue(fn () => TenantLogo::url(), null, false) : null;
        $name = trim((string) $settings->get('company_name'));

        return [
            'name' => $tenant ? ($name !== '' && $name !== 'LENDR' ? $name : $tenant->name) : 'LENDR',
            'logo' => $logo ?? '/brand/lendr-logo.png',
            'is_tenant' => $tenant !== null && $logo !== null,
            'color' => $color,
            // Text on a brand-coloured button: dark on pale brands, white otherwise.
            'on_color' => self::luminance($color) > 0.55 ? '#111827' : '#FFFFFF',
            'tint' => self::mix($color, '#FFFFFF', 0.08),
        ];
    }

    private static function homeUrl(Request $request): string
    {
        if ($request->is('app', 'app/*')) {
            return url('/app');
        }

        $signedIn = rescue(fn () => auth()->check(), false, false);

        return $signedIn ? url('/dashboard') : url('/');
    }

    private static function hex(?string $value): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? strtoupper($value) : null;
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function mix(string $hex, string $with, float $weight): string
    {
        $a = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $b = array_map('hexdec', str_split(ltrim($with, '#'), 2));

        return sprintf('#%02X%02X%02X', ...array_map(
            fn ($x, $y) => (int) round($x * $weight + $y * (1 - $weight)), $a, $b,
        ));
    }
}
