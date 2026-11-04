<?php

namespace App\Listeners;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;

/**
 * Each workspace has its own roles and permissions tables, but spatie's registrar
 * is a singleton built before tenancy starts: it kept one cache key (and one
 * in-memory copy) for every workspace, so one tenant's permission map could be
 * served to another for up to 24 hours, and a queue worker carried it across
 * tenants for its whole life.
 */
class ScopePermissionCacheToTenant
{
    public function handle(TenancyBootstrapped|RevertedToCentralContext $event): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Re-read the cache store: tenancy has swapped in a tenant-scoped one.
        $registrar->initializeCache();

        if ($event instanceof TenancyBootstrapped) {
            $registrar->cacheKey = config('permission.cache.key').'.tenant.'.$event->tenancy->tenant->getTenantKey();
        }

        $registrar->clearPermissionsCollection();
    }
}
