<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The current tenant's logo: one storage location and one URL for every
 * consumer (admin settings, branding API, PWA, emails).
 *
 * Files live on the tenant-suffixed "public" disk, which has no public URL of
 * its own, so they're served by EmailAssetController on the central domain.
 * Raster formats only — SVG can't be shown by mail clients and could carry
 * script when served from our origin.
 */
class TenantLogo
{
    public const SETTING = 'company_logo_path';

    public const DISK = 'public';

    /** Validation rule for uploads. */
    public const RULES = ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

    public static function path(): ?string
    {
        return rescue(fn () => DB::table('settings')->where('key', self::SETTING)->value('value'), null, false);
    }

    public static function url(?string $path = null): ?string
    {
        $path ??= self::path();

        if (! $path) {
            return null;
        }

        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        if (! $tenant) {
            return Storage::disk(self::DISK)->url($path);
        }

        return rtrim((string) config('app.url'), '/').route('email.brand-logo', [
            'tenant' => $tenant->getTenantKey(),
            'v' => substr(md5($path), 0, 8), // cache-bust when the logo changes
        ], false);
    }

    /** Stores a new logo (replacing the old file) and returns its URL. */
    public static function store(UploadedFile $file): string
    {
        $old = self::path();
        $path = $file->store('branding/logos', self::DISK);

        DB::table('settings')->updateOrInsert(
            ['key' => self::SETTING],
            ['value' => $path, 'updated_at' => now()],
        );

        if ($old && $old !== $path) {
            rescue(fn () => Storage::disk(self::DISK)->delete($old), null, false);
        }

        self::forgetCaches();

        return self::url($path);
    }

    public static function delete(): void
    {
        if ($old = self::path()) {
            rescue(fn () => Storage::disk(self::DISK)->delete($old), null, false);
        }

        DB::table('settings')->where('key', self::SETTING)->delete();

        self::forgetCaches();
    }

    private static function forgetCaches(): void
    {
        $id = tenant('id');
        Cache::forget("tenant_settings_{$id}");
        Cache::forget("tenant_branding_{$id}");
    }
}
