<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block all authenticated requests for suspended, cancelled or lapsed tenants.
 * Applied inside the auth middleware group for all tenant routes (web and staff API).
 *
 *   - suspended / cancelled → the landlord's decision: contact support.
 *   - expired, or a trial past its end date → choose a plan (billing stays reachable).
 */
class CheckTenantStatus
{
    private const BLOCKED = ['suspended', 'cancelled'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return $next($request);
        }

        // Allow logout so the user is never permanently trapped.
        if ($request->routeIs('logout', 'portal.logout', 'api.v1.auth.logout')) {
            return $next($request);
        }

        if (in_array($tenant->status, self::BLOCKED, true)) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => 'This workspace is '.$tenant->status.'. Contact LENDR support.',
                    'workspace_status' => $tenant->status,
                ], 403);
            }

            return Inertia::render('account/Suspended', [
                'status' => $tenant->status,
                'tenantName' => $tenant->name,
                'supportEmail' => config('lendr.support_email'),
            ])->toResponse($request)->setStatusCode(403);
        }

        // "expired" is what lendr:process-trial-expiry sets once a trial lapses;
        // a trial past its end date before that job runs is treated the same.
        if ($tenant->status === 'expired' || $tenant->isTrialExpired()) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => 'This workspace\'s trial or plan has ended. Choose a plan to restore access.',
                    'workspace_status' => 'expired',
                ], 402);
            }

            return Inertia::render('account/TrialExpired', [
                'tenantName' => $tenant->name,
                'trialEndedAt' => $tenant->trial_ends_at?->format('d M Y'),
            ])->toResponse($request)->setStatusCode(402);
        }

        return $next($request);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'));
    }
}
