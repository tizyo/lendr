<?php

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the workspace from the {tenant} route segment, for requests that carry
 * no session and arrive on the shared host: payment-provider webhooks
 * (lendr.cc/api/v1/webhooks/pawapay/{tenant}). Without it they ran against the
 * central database and every callback was lost.
 *
 * The segment only says where to look; each webhook still verifies the provider's
 * signature with that workspace's own secret.
 */
class InitializeTenancyFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->route('tenant');

        if (! is_string($id) || $id === '') {
            return response()->json(['message' => 'Unknown workspace.'], 404);
        }

        $tenant = Tenant::find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Unknown workspace.'], 404);
        }

        if (tenancy()->tenant?->getTenantKey() !== $tenant->getTenantKey()) {
            tenancy()->initialize($tenant);
        }

        // Webhooks don't use the parameter themselves.
        $request->route()->forgetParameter('tenant');

        return $next($request);
    }
}
