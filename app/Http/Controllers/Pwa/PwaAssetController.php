<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Services\PwaBrand;
use App\Services\TenantLogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * What makes the borrower PWA installable: a per-tenant web app manifest,
 * the service worker (served from /app/ so it can control every PWA page)
 * and home-screen icons generated from the tenant's logo or brand colour.
 */
class PwaAssetController extends Controller
{
    private const ICON_SIZES = [180, 192, 512];

    public function manifest(): JsonResponse
    {
        $brand = PwaBrand::current();
        $icon = fn (int $size, string $purpose) => [
            'src' => route('pwa.icon', ['size' => $size, 'purpose' => $purpose, 'v' => $brand['version']], false),
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()->json([
            'id' => '/app/',
            'name' => $brand['name'],
            'short_name' => $brand['short_name'],
            'description' => "Manage your {$brand['company']} loans, repayments and statements.",
            'start_url' => '/app/dashboard',
            'scope' => '/app/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#FFFFFF',
            'theme_color' => $brand['theme_color'],
            'icons' => [
                $icon(192, 'any'),
                $icon(512, 'any'),
                $icon(192, 'maskable'),
                $icon(512, 'maskable'),
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache']);
    }

    public function serviceWorker(): Response
    {
        return response(file_get_contents(resource_path('js/pwa/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // Always re-check, so a deployed fix reaches installed apps on their next launch.
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/app/',
        ]);
    }

    public function icon(int $size, string $purpose = 'any'): Response
    {
        abort_unless(in_array($size, self::ICON_SIZES, true) && in_array($purpose, ['any', 'maskable'], true), 404);

        $png = $this->renderIcon(PwaBrand::current(), $size, $purpose === 'maskable');

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800', // URLs carry ?v=, so a brand change gets a new URL
        ]);
    }

    // ─── Icon rendering (GD) ──────────────────────────────────────────────────

    private function renderIcon(array $brand, int $size, bool $maskable): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        $logo = $this->loadLogo($brand['logo_path']);

        if ($logo) {
            // Logo on white, kept inside the maskable safe zone (centre 80%) with breathing room.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            $this->placeLogo($canvas, $logo, $size, $maskable ? 0.56 : 0.72);
            imagedestroy($logo);
        } else {
            [$r, $g, $b] = sscanf($brand['theme_color'], '#%02x%02x%02x');
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
            $this->drawInitial($canvas, $brand['company'], $size, $maskable ? 0.42 : 0.52, $this->textColorFor($r, $g, $b));
        }

        ob_start();
        imagepng($canvas, null, 9);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    private function loadLogo(?string $path): ?\GdImage
    {
        if (! $path) {
            return null;
        }

        $bytes = rescue(fn () => Storage::disk(TenantLogo::DISK)->get($path), null, false);
        $image = $bytes ? @imagecreatefromstring($bytes) : false;

        return $image instanceof \GdImage ? $image : null;
    }

    private function placeLogo(\GdImage $canvas, \GdImage $logo, int $size, float $fraction): void
    {
        $box = (int) round($size * $fraction);
        [$w, $h] = [imagesx($logo), imagesy($logo)];
        $scale = min($box / $w, $box / $h);
        [$dw, $dh] = [(int) round($w * $scale), (int) round($h * $scale)];

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $logo, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
    }

    private function drawInitial(\GdImage $canvas, string $name, int $size, float $fraction, int $color): void
    {
        $letter = mb_strtoupper(mb_substr(trim($name) ?: 'L', 0, 1));
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! is_file($font) || ! function_exists('imagettftext')) {
            return; // plain brand-colour tile is still a valid icon
        }

        $fontSize = $size * $fraction * 0.75; // points → roughly px at GD's 96dpi
        $box = imagettfbbox($fontSize, 0, $font, $letter);
        $textW = $box[2] - $box[0];
        $textH = $box[1] - $box[7];

        imagettftext(
            $canvas, $fontSize, 0,
            (int) (($size - $textW) / 2 - $box[0]),
            (int) (($size + $textH) / 2 - $box[1]),
            $color, $font, $letter,
        );
    }

    /** White on dark brand colours, near-black on pale ones. */
    private function textColorFor(int $r, int $g, int $b): int
    {
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.65 ? 0x111827 : 0xFFFFFF;
    }
}
