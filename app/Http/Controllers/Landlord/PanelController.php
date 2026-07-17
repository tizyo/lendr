<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the Landlord SPA pages via Inertia.
 * Authentication is handled client-side via the landlord API token stored in
 * localStorage; these routes are unprotected at the Laravel level because the
 * panel is a separate domain concern (central domain only, no tenancy).
 */
class PanelController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('landlord/Login');
    }

    public function dashboard(): Response
    {
        return Inertia::render('landlord/Dashboard');
    }

    public function tenants(): Response
    {
        return Inertia::render('landlord/Tenants');
    }

    public function planConfigs(): Response
    {
        return Inertia::render('landlord/PlanConfigs');
    }

    public function billingSettings(): Response
    {
        return Inertia::render('landlord/BillingSettings');
    }

    public function support(): Response
    {
        return Inertia::render('landlord/Support');
    }

    public function platformSettings(): Response
    {
        return Inertia::render('landlord/PlatformSettings');
    }

    public function featuredItems(): Response
    {
        return Inertia::render('landlord/FeaturedItems');
    }
}
