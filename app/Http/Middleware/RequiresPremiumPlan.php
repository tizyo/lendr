<?php

namespace App\Http\Middleware;

use App\Support\PlanUpgrade;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate PWA and other premium features to Growth/Enterprise tenants only.
 * Starter/Trial tenants are shown the upgrade page instead.
 */
class RequiresPremiumPlan
{
    private const PREMIUM_PLANS = ['growth', 'enterprise'];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        // No tenant context (shouldn't happen for PWA routes) — pass through.
        if (! $tenant || in_array($tenant->plan, self::PREMIUM_PLANS)) {
            return $next($request);
        }

        return PlanUpgrade::page($request, 'pwa');
    }
}
