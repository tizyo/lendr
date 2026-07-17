<?php

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified tenancy initialization middleware.
 *
 * - Custom-subdomain tenants  (Growth/Enterprise):  identified by domain lookup.
 * - Shared-portal tenants     (Starter/Trial):       identified by tenant_id stored in session after login.
 * - Central domains (127.0.0.1, localhost, lendr.app): pass through without tenant context.
 */
class InitializeTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        $host           = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // Custom subdomain (Growth/Enterprise) — identify by domain record.
        if (! in_array($host, $centralDomains)) {
            $domain = Domain::where('domain', $host)->first();
            if ($domain) {
                tenancy()->initialize($domain->tenant);
            }
            return $next($request);
        }

        // Central / shared-portal domain — identify by session (Starter/Trial after portal login).
        // hasSession() guards against API requests that run without session middleware.
        //
        // Guard: only initialize tenancy if the user has an active auth session.
        // Without this check, visiting /login with a stale tenant_id in the session
        // would switch the DB to the tenant before auth()->check() runs, causing
        // "Table 'users' doesn't exist" because users is a central-only table.
        $tenantId = $request->hasSession() ? $request->session()->get('tenant_id') : null;
        if ($tenantId) {
            $webGuardKey   = app('auth')->guard('web')->getName();
            $hasAuthSession = $request->session()->has($webGuardKey);

            if ($hasAuthSession) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            }
        }

        return $next($request);
    }
}
