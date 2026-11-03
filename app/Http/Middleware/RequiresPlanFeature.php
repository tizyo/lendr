<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatureService;
use App\Support\PlanUpgrade;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate any route behind a plan feature key.
 *
 * Usage in routes:
 *   ->middleware('plan.feature:pwa')
 *   ->middleware('plan.feature:bulk_operations')
 *   ->middleware('plan.feature:advanced_reports')
 *
 * The feature key must match a boolean key in plan_configs.features JSON.
 * Without it the workspace sees the upgrade page (see PlanUpgrade).
 */
class RequiresPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! tenancy()->tenant || PlanFeatureService::forTenant()->has($feature)) {
            return $next($request);
        }

        return PlanUpgrade::page($request, $feature);
    }
}
