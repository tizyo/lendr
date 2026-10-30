<?php

use App\Http\Middleware\EnforceImpersonationSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        \App\Commands\ProcessOverdueLoansCommand::class,
        \App\Commands\ProcessUpcomingPaymentRemindersCommand::class,
        \App\Commands\ProcessTrialExpiryCommand::class,
        \App\Commands\SendBorrowerStatementsCommand::class,
        \App\Commands\AccrueInterestCommand::class,
        \App\Commands\ApplyPenaltiesCommand::class,
        \App\Commands\ProcessCampaignsCommand::class,
        \App\Commands\ExpireKycDocumentsCommand::class,
        \App\Commands\EscalateCollectionsCommand::class,
        \App\Commands\ProcessStandingOrdersCommand::class,
        \App\Commands\AccrueSavingsInterestCommand::class,
        \App\Commands\ProcessComplianceRemindersCommand::class,
        \App\Commands\BackupTenantDatabasesCommand::class,
        \App\Commands\RunReconciliationCommand::class,
        \App\Commands\ProcessCrmFollowupsCommand::class,
        \App\Commands\CheckPendingPayoutsCommand::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Landlord / Super Admin API routes (central DB, no tenant context).
            // Bearer tokens only: Sanctum checks a session before a token, so a tenant
            // login in the same browser would otherwise replace the landlord's identity.
            \Illuminate\Support\Facades\Route::middleware('api')
                ->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])
                ->prefix('api')
                ->group(base_path('routes/landlord.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnforceImpersonationSession::class,
        ]);

        // API: SPA session auth (for Inertia admin axios calls) + tenant initialization
        $middleware->api(prepend: [EnsureFrontendRequestsAreStateful::class]);
        $middleware->api(append: [InitializeTenancy::class, EnforceImpersonationSession::class]);
        $middleware->throttleApi();

        // Laravel runs auth ahead of ordinary middleware, which looked staff, borrowers
        // and their tokens up in the central database and bounced them back to login.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: InitializeTenancy::class,
        );

        // Ahead of tenancy, which drops a session that reached another tenant's host:
        // a support session must close its audit record before that happens.
        $middleware->prependToPriorityList(
            before: InitializeTenancy::class,
            prepend: EnforceImpersonationSession::class,
        );

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'premium.plan' => \App\Http\Middleware\RequiresPremiumPlan::class,
            'plan.feature' => \App\Http\Middleware\RequiresPlanFeature::class,
            'tenant.status' => \App\Http\Middleware\CheckTenantStatus::class,
            'api-gateway' => \App\Http\Middleware\ApiGatewayAuth::class,
            'marketplace' => \App\Http\Middleware\RequiresMarketplaceEnabled::class,
            'landlord.token' => \App\Http\Middleware\EnsureLandlordToken::class,
        ]);

        // Exclude mobile money webhook routes from CSRF (they use HMAC signature verification instead)
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        SentryIntegration::handles($exceptions);
    })->create();
