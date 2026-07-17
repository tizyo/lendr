<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatureService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
 */
class RequiresPlanFeature
{
    /** Human-readable labels for known feature keys. */
    private const LABELS = [
        'pwa'                       => 'Borrower Self-Service PWA',
        'custom_domain'             => 'Custom Domain',
        'bulk_operations'           => 'Bulk Operations',
        'advanced_reports'          => 'Advanced Reports',
        'collection_management'     => 'Collection Management',
        'marketplace'               => 'Marketplace',
        'disbursement_mobile_money' => 'Mobile Money Disbursement',
        'tenant_website'            => 'Tenant Website',
        'api_access'                => 'API Access',
        'exchange_rates'            => 'Exchange Rates',
        'audit_log'                 => 'Audit Log',
        'two_factor_auth'           => 'Two-Factor Authentication',
    ];

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return $next($request);
        }

        $service = PlanFeatureService::forTenant();

        if ($service->has($feature)) {
            return $next($request);
        }

        $featureLabel = self::LABELS[$feature] ?? ucwords(str_replace('_', ' ', $feature));

        return Inertia::render('upgrade/PremiumRequired', [
            'feature'     => $featureLabel,
            'currentPlan' => ucfirst($tenant->plan),
            'tenantName'  => $tenant->name,
        ])->toResponse($request)->setStatusCode(402);
    }
}
