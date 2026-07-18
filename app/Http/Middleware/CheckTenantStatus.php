<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block all authenticated requests for suspended or cancelled tenants.
 * Applied inside the auth middleware group for all tenant routes.
 */
class CheckTenantStatus
{
    private const BLOCKED = ['suspended', 'cancelled', 'expired'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return $next($request);
        }

        // Allow logout so the user is never permanently trapped.
        if ($request->routeIs('logout', 'portal.logout')) {
            return $next($request);
        }

        if (in_array($tenant->status, self::BLOCKED)) {
            return Inertia::render('account/Suspended', [
                'status' => $tenant->status,
                'tenantName' => $tenant->name,
            ])->toResponse($request)->setStatusCode(403);
        }

        if ($tenant->isTrialExpired()) {
            return Inertia::render('account/TrialExpired', [
                'tenantName' => $tenant->name,
                'trialEndedAt' => $tenant->trial_ends_at?->format('d M Y'),
            ])->toResponse($request)->setStatusCode(402);
        }

        return $next($request);
    }
}
