<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Mail\TenantVerificationMail;
use App\Models\Landlord\PlanConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    private const RESERVED_SUBDOMAINS = [
        'www', 'api', 'app', 'admin', 'mail', 'smtp', 'ftp', 'cdn',
        'static', 'assets', 'media', 'status', 'help', 'support',
        'dev', 'staging', 'test', 'demo', 'beta', 'lendr',
    ];

    /** Plans that share the portal (no custom subdomain). */
    private const SHARED_PORTAL_PLANS = ['starter', 'trial'];

    public function show(): Response
    {
        $centralDomain = env('CENTRAL_DOMAIN', 'localhost');
        $sharedPortalHost = 'app.'.$centralDomain;

        $configs = PlanConfig::allKeyed();

        return Inertia::render('onboarding/Index', [
            'sharedPortalHost' => $sharedPortalHost,
            'plans' => $this->buildPlanCards($configs),
            'currencies' => [
                ['code' => 'ZMW', 'label' => 'ZMW — Zambian Kwacha'],
                ['code' => 'USD', 'label' => 'USD — US Dollar'],
                ['code' => 'KES', 'label' => 'KES — Kenyan Shilling'],
                ['code' => 'UGX', 'label' => 'UGX — Ugandan Shilling'],
                ['code' => 'TZS', 'label' => 'TZS — Tanzanian Shilling'],
                ['code' => 'NGN', 'label' => 'NGN — Nigerian Naira'],
                ['code' => 'GHS', 'label' => 'GHS — Ghanaian Cedi'],
                ['code' => 'ZAR', 'label' => 'ZAR — South African Rand'],
                ['code' => 'MWK', 'label' => 'MWK — Malawian Kwacha'],
                ['code' => 'MZN', 'label' => 'MZN — Mozambican Metical'],
            ],
            'timezones' => [
                ['value' => 'Africa/Lusaka',      'label' => 'Africa/Lusaka (CAT)'],
                ['value' => 'Africa/Nairobi',     'label' => 'Africa/Nairobi (EAT)'],
                ['value' => 'Africa/Lagos',        'label' => 'Africa/Lagos (WAT)'],
                ['value' => 'Africa/Johannesburg', 'label' => 'Africa/Johannesburg (SAST)'],
                ['value' => 'Africa/Cairo',        'label' => 'Africa/Cairo (EET)'],
                ['value' => 'Africa/Accra',        'label' => 'Africa/Accra (GMT)'],
                ['value' => 'Africa/Dar_es_Salaam', 'label' => 'Africa/Dar es Salaam (EAT)'],
                ['value' => 'Africa/Kampala',      'label' => 'Africa/Kampala (EAT)'],
                ['value' => 'Africa/Harare',       'label' => 'Africa/Harare (CAT)'],
                ['value' => 'Africa/Maputo',       'label' => 'Africa/Maputo (CAT)'],
                ['value' => 'UTC',                 'label' => 'UTC'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $centralDomain = env('CENTRAL_DOMAIN', 'localhost');
        $sharedPortalHost = 'app.'.$centralDomain;

        $usesSharedPortal = in_array($request->input('plan'), self::SHARED_PORTAL_PLANS);

        $rules = [
            'org_name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'regex:/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/',
                'max:30',
                function ($attr, $value, $fail) {
                    if (DB::table('tenants')->where('slug', $value)->exists()) {
                        $fail('This workspace name is already taken.');
                    }
                },
            ],
            'plan' => ['required', Rule::in(['starter', 'growth', 'enterprise'])],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:60'],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:191'],
            'admin_phone' => ['nullable', 'string', 'max:20'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        // Growth/Enterprise get a custom subdomain.
        if (! $usesSharedPortal) {
            $rules['subdomain'] = [
                'required',
                'regex:/^[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]$/',
                'max:30',
                function ($attr, $value, $fail) use ($centralDomain) {
                    if (in_array(strtolower($value), self::RESERVED_SUBDOMAINS)) {
                        $fail('This subdomain is reserved. Please choose a different one.');

                        return;
                    }
                    $domain = $value.'.'.$centralDomain;
                    if (DB::table('domains')->where('domain', $domain)->exists()) {
                        $fail('This subdomain is already taken.');
                    }
                },
            ];
        }

        $data = $request->validate($rules);

        $slug = strtolower($data['slug']);

        $verificationToken = Str::random(64);

        // Create tenant (TenancyServiceProvider creates DB + runs tenant migrations automatically).
        $tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'name' => $data['org_name'],
            'slug' => $slug,
            'plan' => $data['plan'],
            'status' => 'trial',
            'currency' => strtoupper($data['currency']),
            'timezone' => $data['timezone'],
            'admin_email' => $data['admin_email'],
            'email_verification_token' => $verificationToken,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $loginUrl = null;

        if ($usesSharedPortal) {
            // Starter/Trial: shared portal, no subdomain record needed.
            $loginUrl = 'http://'.$sharedPortalHost.'/portal/login';
        } else {
            // Growth/Enterprise: custom subdomain.
            $subdomain = strtolower($data['subdomain']);
            $domain = $subdomain.'.'.$centralDomain;
            $tenant->domains()->create(['domain' => $domain]);
            $loginUrl = 'http://'.$domain.'/login';
        }

        // Switch into tenant context and create the SuperAdmin.
        // withoutEvents() suppresses activity log during provisioning.
        tenancy()->initialize($tenant);

        try {
            User::withoutEvents(function () use ($data) {
                User::create([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'phone' => $data['admin_phone'] ?? null,
                    'password' => $data['admin_password'],
                    'role' => UserRole::SuperAdmin,
                    'is_active' => false, // activated after email verification
                ]);
            });
        } finally {
            tenancy()->end();
        }

        // Send verification email (central domain URL — no tenancy context needed).
        $verificationUrl = route('onboarding.verify', $verificationToken);
        Mail::to($data['admin_email'])->queue(new TenantVerificationMail($tenant, $verificationUrl));

        return redirect()->route('onboarding.success')
            ->with('slug', $slug)
            ->with('plan', $data['plan'])
            ->with('login_url', $loginUrl)
            ->with('org_name', $data['org_name'])
            ->with('admin_email', $data['admin_email'])
            ->with('needs_verification', true);
    }

    public function success(): Response
    {
        if (! session()->has('slug')) {
            return Inertia::render('onboarding/Index', []);
        }

        return Inertia::render('onboarding/Success', [
            'slug' => session('slug'),
            'plan' => session('plan'),
            'loginUrl' => session('login_url'),
            'orgName' => session('org_name'),
            'adminEmail' => session('admin_email'),
            'needsVerification' => session('needs_verification', false),
        ]);
    }

    /**
     * GET /onboarding/verify/{token}
     * Verify the tenant admin's email address and activate the account.
     */
    public function verifyEmail(string $token): RedirectResponse
    {
        $tenant = Tenant::where('email_verification_token', $token)->first();

        if (! $tenant) {
            return redirect()->route('onboarding')
                ->withErrors(['token' => 'This verification link is invalid or has already been used.']);
        }

        // Activate the tenant and mark email as verified.
        $tenant->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ]);

        // Activate the super admin user inside the tenant's database.
        tenancy()->initialize($tenant);
        try {
            User::where('email', $tenant->admin_email)->update(['is_active' => true]);
        } finally {
            tenancy()->end();
        }

        $loginUrl = $tenant->usesSharedPortal()
            ? route('portal.login')
            : 'http://'.optional($tenant->domains->first())->domain.'/login';

        return redirect($loginUrl)->with('success', 'Email verified! Your account is now active. Please log in.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build the plan card data for the onboarding page from live PlanConfig records.
     * Falls back to a safe default if the table isn't seeded yet.
     *
     * @param  array<string, PlanConfig>  $configs  Keyed by plan slug.
     */
    private function buildPlanCards(array $configs): array
    {
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

        $plans = [];

        foreach (['starter', 'growth', 'enterprise'] as $planKey) {
            /** @var PlanConfig|null $cfg */
            $cfg = $configs[$planKey] ?? null;
            $features = $cfg?->features ?? [];

            // Build human-readable feature list (enabled booleans + limits)
            $featureList = [];
            $unavailableList = [];

            if (isset($features['max_borrowers'])) {
                $featureList[] = $features['max_borrowers'] === -1
                    ? 'Unlimited borrowers'
                    : 'Up to '.number_format($features['max_borrowers']).' borrowers';
            }
            if (isset($features['max_branches'])) {
                $featureList[] = $features['max_branches'] === -1
                    ? 'Unlimited branches'
                    : $features['max_branches'].' branch'.($features['max_branches'] > 1 ? 'es' : '');
            }
            if (isset($features['max_users'])) {
                $featureList[] = $features['max_users'] === -1
                    ? 'Unlimited staff'
                    : 'Up to '.$features['max_users'].' staff';
            }

            foreach ($boolLabels as $key => $label) {
                if (! isset($features[$key])) {
                    continue;
                }
                if ($features[$key]) {
                    $featureList[] = $label;
                } else {
                    $unavailableList[] = $label;
                }
            }

            $subdomain = in_array($planKey, self::SHARED_PORTAL_PLANS) ? false : true;

            if ($cfg?->is_custom_price) {
                $price = 'Custom';
            } elseif ((float) ($cfg?->price_zmw ?? 0) === 0.0) {
                $price = 'Free';
            } else {
                $price = 'ZMW '.number_format((float) $cfg->price_zmw, 2).'/mo';
            }

            $plans[] = [
                'key' => $planKey,
                'label' => $cfg?->label ?? ucfirst($planKey),
                'price' => $price,
                'subdomain' => $subdomain,
                'description' => $cfg?->description ?? '',
                'features' => $featureList,
                'unavailable' => $unavailableList,
            ];
        }

        return $plans;
    }
}
