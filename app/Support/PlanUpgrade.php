<?php

namespace App\Support;

use App\Models\Landlord\PlanConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "this needs a higher plan" page shown in place of a gated feature: it keeps
 * the app's navigation, compares the workspace's plan with the cheapest plan that
 * includes the feature (live from plan_configs, in Kwacha), and sends the upgrade
 * button to Billing with that plan preselected.
 */
class PlanUpgrade
{
    /** Plans from cheapest to most complete. */
    public const ORDER = ['starter', 'growth', 'enterprise'];

    /** Human-readable labels for plan feature keys. */
    public const LABELS = [
        'pwa' => 'Borrower Self-Service PWA',
        'custom_domain' => 'Custom Domain',
        'bulk_operations' => 'Bulk Operations',
        'advanced_reports' => 'Advanced Reports',
        'collection_management' => 'Collection Management',
        'marketplace' => 'Marketplace',
        'disbursement_mobile_money' => 'Mobile Money Disbursement',
        'tenant_website' => 'Tenant Website',
        'api_access' => 'API Access',
        'exchange_rates' => 'Exchange Rates',
        'audit_log' => 'Audit Log',
        'two_factor_auth' => 'Two-Factor Authentication',
    ];

    private const LIMITS = [
        'max_borrowers' => 'Borrowers',
        'max_users' => 'Staff accounts',
        'max_branches' => 'Branches',
        'max_loan_products' => 'Loan products',
    ];

    public static function label(string $feature): string
    {
        return self::LABELS[$feature] ?? ucwords(str_replace('_', ' ', $feature));
    }

    public static function page(Request $request, string $feature): Response
    {
        // Borrowers can't upgrade anything: on the borrower app, say plainly it isn't on.
        if ($request->is('app', 'app/*')) {
            $name = tenancy()->tenant?->name ?? 'Your lender';

            return Inertia::render('Error', [
                ...ErrorPage::props(403, $request),
                'title' => 'App not available yet',
                'message' => "{$name} hasn't switched on the borrower app yet. Please contact them directly about your loan.",
            ])->toResponse($request)->setStatusCode(403);
        }

        return Inertia::render('upgrade/PremiumRequired', self::props($feature))
            ->toResponse($request)
            ->setStatusCode(402);
    }

    /** @return array<string, mixed> */
    public static function props(string $feature): array
    {
        $tenant = tenancy()->tenant;
        $plans = PlanConfig::allKeyed();
        $currentKey = in_array($tenant?->plan, self::ORDER, true) ? $tenant->plan : 'starter';

        $current = $plans[$currentKey] ?? null;
        $recommended = null;
        foreach (array_slice(self::ORDER, array_search($currentKey, self::ORDER, true) + 1) as $key) {
            if (isset($plans[$key]) && $plans[$key]->hasFeature($feature)) {
                $recommended = $plans[$key];
                break;
            }
        }

        // The rows worth comparing: features either plan includes, the gated one first.
        $featureKeys = collect(array_keys(self::LABELS))
            ->filter(fn ($k) => $current?->hasFeature($k) || $recommended?->hasFeature($k))
            ->sortBy(fn ($k) => $k === $feature ? 0 : 1)
            ->values();

        return [
            'feature' => self::label($feature),
            'featureKey' => $feature,
            'tenantName' => $tenant?->name,
            'current' => $current ? self::card($current, $featureKeys->all()) : null,
            'recommended' => $recommended ? self::card($recommended, $featureKeys->all()) : null,
        ];
    }

    /** @param  list<string>  $featureKeys */
    private static function card(PlanConfig $plan, array $featureKeys): array
    {
        return [
            'key' => $plan->plan,
            'label' => $plan->label,
            'description' => $plan->description,
            'price_zmw' => (float) $plan->price_zmw,
            'is_custom_price' => (bool) $plan->is_custom_price,
            'limits' => collect(self::LIMITS)->map(fn ($label, $key) => [
                'label' => $label,
                'value' => match (true) {
                    (int) $plan->feature($key, 0) === -1 => 'Unlimited',
                    default => number_format((int) $plan->feature($key, 0)),
                },
            ])->values()->all(),
            'features' => array_map(fn ($k) => [
                'key' => $k,
                'label' => self::label($k),
                'included' => $plan->hasFeature($k),
            ], $featureKeys),
        ];
    }
}
