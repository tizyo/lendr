<?php

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initializes tenancy for shared-portal routes (Starter/Trial plans).
 * Reads the tenant_id stored in the session after the shared-portal login.
 */
class InitializeTenancyBySession
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }
}
