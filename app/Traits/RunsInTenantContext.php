<?php

namespace App\Traits;

use App\Models\Landlord\Tenant;

trait RunsInTenantContext
{
    /** Tenant::run() skips its clean-up when the callback throws, e.g. on validation. */
    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }
}
