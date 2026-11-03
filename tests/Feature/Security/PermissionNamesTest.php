<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Finder\Finder;

/*
 * A check against a permission that doesn't exist denies everyone, silently:
 * "settings.manage" blocked every loan type and plan, "funds.approve" every fund
 * deposit approval. Every permission named in routes, PHP and Vue must be one the
 * roles seeder (or a tenant migration) actually creates.
 */

it('only checks permissions that exist', function () {
    // The test database has already run every migration, so permissions added by a
    // migration (prod never re-seeds) are present alongside the seeded ones.
    (new RolesAndPermissionsSeeder)->run();
    $known = Permission::pluck('name')->all();

    $patterns = [
        '/permission:([a-z_|.]+)/',
        "/->can\\(\\s*'([a-z_]+\\.[a-z_]+)'/",
        "/\\bcan\\(\\s*'([a-z_]+\\.[a-z_]+)'\\s*\\)/",
        "/hasPermissionTo\\(\\s*'([a-z_]+\\.[a-z_]+)'/",
    ];

    $unknown = [];
    $files = Finder::create()->files()->in([base_path('routes'), app_path(), resource_path('js')])->name(['*.php', '*.vue', '*.js']);

    foreach ($files as $file) {
        $source = $file->getContents();
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $source, $matches);
            foreach ($matches[1] as $group) {
                foreach (explode('|', $group) as $name) {
                    if (str_contains($name, '.') && ! in_array($name, $known, true)) {
                        $unknown[$name][] = $file->getRelativePathname();
                    }
                }
            }
        }
    }

    expect($unknown)->toBe([]);
});
