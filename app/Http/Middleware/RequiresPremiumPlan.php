<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate PWA and other premium features to Growth/Enterprise tenants only.
 * Starter/Trial tenants are shown an upgrade prompt instead.
 */
class RequiresPremiumPlan
{
    private const PREMIUM_PLANS = ['growth', 'enterprise'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        // No tenant context (shouldn't happen for PWA routes) — pass through.
        if (! $tenant) {
            return $next($request);
        }

        if (in_array($tenant->plan, self::PREMIUM_PLANS)) {
            return $next($request);
        }

        // Starter / Trial — return upgrade prompt page.
        return Inertia::render('upgrade/PremiumRequired', [
            'feature' => 'Borrower Self-Service PWA',
            'currentPlan' => ucfirst($tenant->plan),
            'tenantName' => $tenant->name,
        ])->toResponse($request)->setStatusCode(402);
    }
}
