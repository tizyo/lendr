<?php

namespace App\Http\Controllers;

use App\Models\Landlord\PlanConfig;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Landing/Home', [
            'plans' => $this->buildPricingPlans(),
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Landing/About');
    }

    public function contact(): Response
    {
        return Inertia::render('Landing/Contact');
    }

    public function privacy(): Response
    {
        return Inertia::render('Landing/Privacy');
    }

    public function terms(): Response
    {
        return Inertia::render('Landing/Terms');
    }

    public function marketplace(): Response
    {
        return Inertia::render('Landing/Marketplace');
    }

    public function help(): Response
    {
        return Inertia::render('Landing/Help');
    }

    public function changelog(): Response
    {
        return Inertia::render('Landing/Changelog');
    }

    public function careers(): Response
    {
        return Inertia::render('Landing/Careers');
    }

    public function blog(): Response
    {
        return Inertia::render('Landing/Blog');
    }

    public function docs(): Response
    {
        return Inertia::render('Landing/Docs');
    }

    private function buildPricingPlans(): array
    {
        $configs = PlanConfig::allKeyed();

        // Feature bullet labels per boolean key (positive framing only)
        $boolLabels = [
            'pwa' => 'Borrower self-service PWA',
            'custom_domain' => 'Custom subdomain',
            'bulk_operations' => 'Bulk operations',
            'advanced_reports' => 'Advanced reports',
            'collection_management' => 'Collection management',
            'marketplace' => 'Marketplace',
            'disbursement_mobile_money' => 'Mobile money disbursement',
            'tenant_website' => 'Tenant website',
            'api_access' => 'API access',
            'exchange_rates' => 'Exchange rates',
            'audit_log' => 'Audit log',
        ];

        $result = [];

        foreach (['starter', 'growth', 'enterprise'] as $planKey) {
            /** @var PlanConfig|null $cfg */
            $cfg = $configs[$planKey] ?? null;
            $features = $cfg?->features ?? [];

            $bullets = [];

            // Numeric limits → human bullets
            if (isset($features['max_users'])) {
                $bullets[] = $features['max_users'] === -1
                    ? 'Unlimited staff users'
                    : 'Up to '.$features['max_users'].' staff users';
            }
            if (isset($features['max_borrowers'])) {
                $bullets[] = $features['max_borrowers'] === -1
                    ? 'Unlimited borrowers'
                    : 'Up to '.number_format($features['max_borrowers']).' borrowers';
            }
            if (isset($features['max_branches'])) {
                $bullets[] = $features['max_branches'] === -1
                    ? 'Unlimited branches'
                    : $features['max_branches'].' branch'.($features['max_branches'] > 1 ? 'es' : '');
            }
            if (isset($features['max_loan_products'])) {
                $bullets[] = $features['max_loan_products'] === -1
                    ? 'Unlimited loan products'
                    : 'Up to '.$features['max_loan_products'].' loan products';
            }

            // Enabled boolean features
            foreach ($boolLabels as $key => $label) {
                if (! empty($features[$key])) {
                    $bullets[] = $label;
                }
            }

            // Price display
            if ($cfg?->is_custom_price) {
                $price = 'Custom';
            } elseif ((float) ($cfg?->price_zmw ?? 0) === 0.0) {
                $price = 'Free';
            } else {
                $price = 'K '.number_format((float) $cfg->price_zmw, 0, '.', ',');
            }

            $result[] = [
                'key' => $planKey,
                'name' => $cfg?->label ?? ucfirst($planKey),
                'price' => $price,
                'description' => $cfg?->description ?? '',
                'featured' => $planKey === 'growth',
                'features' => $bullets,
            ];
        }

        return $result;
    }
}
