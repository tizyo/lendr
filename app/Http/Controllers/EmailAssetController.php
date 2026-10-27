<?php

namespace App\Http\Controllers;

use App\Models\Landlord\Tenant;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Public URL for a tenant's logo (see App\Services\TenantLogo), used by the
 * admin settings, branding API, borrower portal and emails.
 *
 * Tenant uploads live on a tenant-suffixed disk that has no public URL, and
 * emails are opened far from any tenant domain — so everything points here,
 * on the central domain. Raster images only: an SVG served from our own origin
 * could carry script.
 */
class EmailAssetController extends Controller
{
    private const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function tenantLogo(string $tenant): Response
    {
        $model = Tenant::find($tenant);

        abort_unless($model, 404);

        $file = $model->run(function () {
            $path = rescue(fn () => DB::table('settings')->where('key', 'company_logo_path')->value('value'), null, false);

            if (! $path) {
                return null;
            }

            // Logos have been uploaded to both disks over time (see BrandingController / SettingController).
            foreach (['public', 's3'] as $disk) {
                $found = rescue(function () use ($disk, $path) {
                    $storage = Storage::disk($disk);

                    return $storage->exists($path) ? [$storage->get($path), $storage->mimeType($path)] : null;
                }, null, false);

                if ($found) {
                    return $found;
                }
            }

            return null;
        });

        abort_unless($file && in_array($file[1], self::ALLOWED_TYPES, true), 404);

        return response($file[0], 200, [
            'Content-Type' => $file[1],
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
