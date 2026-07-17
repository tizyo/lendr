<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BrandingController extends BaseApiController
{
    private const BRANDING_KEYS = [
        'company_name',
        'primary_color',
        'secondary_color',
        'company_logo_path',
        'favicon_path',
        'pwa_app_name',
        'pwa_theme_color',
        'support_email',
        'support_phone',
        'email_header_color',
        'email_footer_text',
        'address',
        'website',
    ];

    /**
     * GET /api/v1/branding
     * Public endpoint — no auth required (used by PWA, login page, etc.)
     */
    public function show(): JsonResponse
    {
        $rows = DB::table('settings')
            ->whereIn('key', self::BRANDING_KEYS)
            ->pluck('value', 'key');

        return $this->success([
            'company_name'      => $rows->get('company_name', 'LENDR'),
            'primary_color'     => $rows->get('primary_color', '#0D47A1'),
            'secondary_color'   => $rows->get('secondary_color', '#1565C0'),
            'logo_url'          => $this->fileUrl($rows->get('company_logo_path')),
            'favicon_url'       => $this->fileUrl($rows->get('favicon_path')),
            'pwa_app_name'      => $rows->get('pwa_app_name', 'LENDR'),
            'pwa_theme_color'   => $rows->get('pwa_theme_color', '#0D47A1'),
            'support_email'     => $rows->get('support_email'),
            'support_phone'     => $rows->get('support_phone'),
            'email_header_color'=> $rows->get('email_header_color', '#0D47A1'),
            'email_footer_text' => $rows->get('email_footer_text', 'Powered by LENDR'),
            'address'           => $rows->get('address'),
            'website'           => $rows->get('website'),
        ]);
    }

    /**
     * PUT /api/v1/branding
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name'       => ['nullable', 'string', 'max:100'],
            'primary_color'      => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color'    => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pwa_app_name'       => ['nullable', 'string', 'max:50'],
            'pwa_theme_color'    => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email'      => ['nullable', 'email', 'max:150'],
            'support_phone'      => ['nullable', 'string', 'max:30'],
            'email_header_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'email_footer_text'  => ['nullable', 'string', 'max:255'],
            'address'            => ['nullable', 'string', 'max:255'],
            'website'            => ['nullable', 'url', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
            }
        }

        return $this->success(null, 'Branding updated.');
    }

    /**
     * POST /api/v1/branding/logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,svg,webp', 'max:2048'],
        ]);

        $path = $request->file('logo')->store('branding/logos', 'public');

        DB::table('settings')->updateOrInsert(
            ['key' => 'company_logo_path'],
            ['value' => $path, 'updated_at' => now()]
        );

        return $this->success(['url' => Storage::disk('public')->url($path)], 'Logo uploaded.');
    }

    /**
     * POST /api/v1/branding/favicon
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => ['required', 'file', 'mimes:ico,png,svg', 'max:512'],
        ]);

        $path = $request->file('favicon')->store('branding/favicons', 'public');

        DB::table('settings')->updateOrInsert(
            ['key' => 'favicon_path'],
            ['value' => $path, 'updated_at' => now()]
        );

        return $this->success(['url' => Storage::disk('public')->url($path)], 'Favicon uploaded.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Handle both s3 (legacy) and local public disk paths
        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
