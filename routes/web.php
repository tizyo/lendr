<?php

use App\Http\Controllers\Admin\AcceptInvitationController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Landlord\PanelController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Pwa\AppController;
use App\Http\Middleware\InitializeTenancy;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Central Domain Routes (127.0.0.1 / localhost / lendr.app)
| No tenancy context — marketing, onboarding.
|--------------------------------------------------------------------------
*/
Route::get('/',           [LandingController::class, 'home'])->name('home');
Route::get('/about',      [LandingController::class, 'about'])->name('about');
Route::get('/contact',    [LandingController::class, 'contact'])->name('contact');
Route::get('/privacy',    [LandingController::class, 'privacy'])->name('privacy');
Route::get('/terms',      [LandingController::class, 'terms'])->name('terms');
Route::get('/marketplace',[LandingController::class, 'marketplace'])->name('marketplace');
Route::get('/help',       [LandingController::class, 'help'])->name('help');
Route::get('/changelog',  [LandingController::class, 'changelog'])->name('changelog');
Route::get('/careers',    [LandingController::class, 'careers'])->name('careers');
Route::get('/blog',       [LandingController::class, 'blog'])->name('blog');
Route::get('/docs',       [LandingController::class, 'docs'])->name('docs');

// ─── Public Repo Marketplace (no tenant context required) ─────────────────
Route::prefix('app')->name('pwa.public.')->group(function () {
    Route::get('/repo',       [AppController::class, 'repoBrowse'])->name('repo.browse');
    Route::get('/repo/{id}',  [AppController::class, 'repoShow'])->name('repo.show');
    Route::get('/repo/auth/login',  [AppController::class, 'ghostLogin'])->name('repo.auth.login');
    Route::get('/repo/auth/verify', [AppController::class, 'ghostVerify'])->name('repo.auth.verify');
});

Route::get('/onboarding',                  [OnboardingController::class, 'show'])->name('onboarding');
Route::post('/onboarding',                 [OnboardingController::class, 'store'])->name('onboarding.store');
Route::get('/onboarding/success',          [OnboardingController::class, 'success'])->name('onboarding.success');
Route::get('/onboarding/verify/{token}',   [OnboardingController::class, 'verifyEmail'])->name('onboarding.verify');

// Staff invitation (central domain — controller initializes tenancy manually)
Route::get('/invitation/{tenant}/{token}',  [AcceptInvitationController::class, 'show'])->name('invitation.show');
Route::post('/invitation/{tenant}/{token}', [AcceptInvitationController::class, 'accept'])->name('invitation.accept');

/*
|--------------------------------------------------------------------------
| Landlord Panel (central domain, no tenancy context)
| Auth is handled client-side via API token (landlord API).
|--------------------------------------------------------------------------
*/
Route::prefix('landlord')->name('landlord.')->group(function () {
    Route::get('/',                [PanelController::class, 'login'])->name('login');
    Route::get('/dashboard',       [PanelController::class, 'dashboard'])->name('dashboard');
    Route::get('/tenants',         [PanelController::class, 'tenants'])->name('tenants');
    Route::get('/plan-configs',    [PanelController::class, 'planConfigs'])->name('plan-configs');
    Route::get('/billing-settings',[PanelController::class, 'billingSettings'])->name('billing-settings');
    Route::get('/support',           [PanelController::class, 'support'])->name('support');
    Route::get('/platform-settings', [PanelController::class, 'platformSettings'])->name('platform-settings');
    Route::get('/featured-items',    [PanelController::class, 'featuredItems'])->name('featured-items');
});

/*
|--------------------------------------------------------------------------
| Shared Portal — login/logout (Starter / Trial)
| app.localhost / app.lendr.app — guest routes only (no tenant context yet).
|--------------------------------------------------------------------------
*/
Route::name('portal.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/portal/login',          [AuthController::class, 'showPortalLogin'])->name('login');
        Route::post('/portal/login',         [AuthController::class, 'portalLogin'])->name('login.post');
        Route::get('/portal/forgot-password',[AuthController::class, 'showPortalForgotPassword'])->name('password.request');
        Route::post('/portal/forgot-password',[AuthController::class, 'sendPortalResetLink'])->name('password.email');
        Route::get('/portal/reset-password', [AuthController::class, 'showPortalResetPassword'])->name('password.reset');
        Route::post('/portal/reset-password',[AuthController::class, 'resetPortalPassword'])->name('password.update');
    });

    Route::post('/portal/logout', [AuthController::class, 'portalLogout'])
        ->middleware('auth')
        ->name('logout');
});

Route::post('/onboarding/resend-verification', [AuthController::class, 'resendVerification'])
    ->name('onboarding.resend-verification');

/*
|--------------------------------------------------------------------------
| All Tenant Routes (subdomain + shared portal)
| InitializeTenancy handles both:
|   - Custom subdomain → domain lookup (Growth/Enterprise)
|   - Shared portal   → session lookup (Starter/Trial)
|--------------------------------------------------------------------------
*/
Route::middleware([InitializeTenancy::class])->group(function () {

    // ─── Auth (guest) ──────────────────────────────────────────────────
    Route::middleware('guest')->group(function () {
        Route::get('/login',              [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login',             [AuthController::class, 'login'])->name('login.post');
        Route::get('/2fa/challenge',      [AuthController::class, 'show2faChallenge'])->name('2fa.challenge');
        Route::post('/2fa/challenge',     [AuthController::class, 'verify2fa'])->name('2fa.verify');
        Route::get('/forgot-password',    [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/forgot-password',   [AuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password',     [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/reset-password',    [AuthController::class, 'resetPassword'])->name('password.update');
    });

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    // ─── Billing (auth only — bypasses tenant.status so expired tenants can pay) ──
    Route::middleware('auth')->group(function () {
        Route::get('/billing',           [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::get('/billing/callback',  [BillingController::class, 'callback'])->name('billing.callback');
    });

    // ─── Authenticated Admin ────────────────────────────────────────────
    Route::middleware(['auth', 'tenant.status'])->group(function () {
        Route::get('/dashboard',         [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/portal/dashboard',  [DashboardController::class, 'index'])->name('portal.dashboard');

        require __DIR__.'/admin/borrowers.php';
        require __DIR__.'/admin/loans.php';
        require __DIR__.'/admin/payments.php';
        require __DIR__.'/admin/funds.php';
        require __DIR__.'/admin/expenses.php';
        require __DIR__.'/admin/staff.php';
        require __DIR__.'/admin/settings.php';
        require __DIR__.'/admin/kyc.php';
        require __DIR__.'/admin/branches.php';
        require __DIR__.'/admin/loan-types.php';
        require __DIR__.'/admin/broadcast.php';

        // Support tickets (all plans)
        Route::get('/support',                         [SupportTicketController::class, 'index'])->name('support.index');
        Route::post('/support',                        [SupportTicketController::class, 'store'])->name('support.store');
        Route::get('/support/{id}',                    [SupportTicketController::class, 'show'])->name('support.show');
        Route::post('/support/{id}/reply',             [SupportTicketController::class, 'reply'])->name('support.reply');

        // ── Plan-feature gated routes ───────────────────────────────────────
        Route::middleware('plan.feature:audit_log')->group(function () {
            require __DIR__.'/admin/audit-log.php';
        });
        Route::middleware('plan.feature:advanced_reports')->group(function () {
            require __DIR__.'/admin/reports.php';
        });
        Route::middleware('plan.feature:exchange_rates')->group(function () {
            require __DIR__.'/admin/exchange-rates.php';
        });
        Route::middleware('plan.feature:marketplace')->group(function () {
            require __DIR__.'/admin/marketplace.php';
        });
        Route::middleware('plan.feature:bulk_operations')->group(function () {
            require __DIR__.'/admin/bulk.php';
        });
        Route::middleware('plan.feature:collection_management')->group(function () {
            require __DIR__.'/admin/collections.php';
        });

        // Phases 29–34
        require __DIR__.'/admin/writeoffs.php';
        require __DIR__.'/admin/savings.php';
        require __DIR__.'/admin/loan-groups.php';
        require __DIR__.'/admin/webhooks.php';

        // Phases 44–60 UI
        require __DIR__.'/admin/approvals.php';
        require __DIR__.'/admin/financial-statements.php';
        require __DIR__.'/admin/investors.php';
        require __DIR__.'/admin/provisioning.php';
        require __DIR__.'/admin/interest-accrual.php';
        require __DIR__.'/admin/penalties.php';
        require __DIR__.'/admin/staff-targets.php';
        require __DIR__.'/admin/reconciliation.php';
        require __DIR__.'/admin/campaigns.php';
        require __DIR__.'/admin/gl-ledger.php';
        require __DIR__.'/admin/analytics.php';
        require __DIR__.'/admin/leads.php';
        require __DIR__.'/admin/insurance.php';
        require __DIR__.'/admin/commissions.php';
        require __DIR__.'/admin/api-clients.php';
        require __DIR__.'/admin/collection-cases.php';
        require __DIR__.'/admin/crb.php';
        require __DIR__.'/admin/featured-items.php';
        require __DIR__.'/admin/hot-deals.php';
    });
});

/*
|--------------------------------------------------------------------------
| Borrower PWA Routes (/app prefix — tenant subdomains)
|--------------------------------------------------------------------------
*/
Route::prefix('app')->name('pwa.')
    ->middleware([InitializeTenancy::class, 'plan.feature:pwa'])
    ->group(function () {
        require __DIR__.'/pwa.php';
    });
