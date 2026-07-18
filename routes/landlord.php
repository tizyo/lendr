<?php

use App\Http\Controllers\Api\V1\Landlord\AuthController;
use App\Http\Controllers\Api\V1\Landlord\BillingSettingsController;
use App\Http\Controllers\Api\V1\Landlord\PlanConfigController;
use App\Http\Controllers\Api\V1\Landlord\PlatformSettingsController;
use App\Http\Controllers\Api\V1\Landlord\SupportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord / Super Admin Routes
|--------------------------------------------------------------------------
| Central DB only — no tenant context.
| All routes protected by LandlordAuth middleware (Sanctum + landlord guard).
|--------------------------------------------------------------------------
*/

Route::prefix('v1/landlord')->name('landlord.')->group(function () {

    // ─── Auth (public) ────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('2fa/setup', [AuthController::class, 'setup2fa'])->name('2fa.setup');
            Route::post('2fa/verify', [AuthController::class, 'verify2fa'])->name('2fa.verify');
            Route::post('2fa/challenge', [AuthController::class, 'challenge2fa'])->name('2fa.challenge');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    // ─── Protected Landlord Routes ────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        // Tenant management
        Route::apiResource('tenants', \App\Http\Controllers\Api\V1\Landlord\TenantController::class);
        Route::post('tenants/{id}/suspend', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'suspend'])->name('landlord.tenants.suspend');
        Route::post('tenants/{id}/activate', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'activate'])->name('landlord.tenants.activate');
        Route::post('tenants/{id}/change-plan', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'changePlan'])->name('landlord.tenants.change-plan');
        Route::post('tenants/{id}/change-status', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'changeStatus'])->name('landlord.tenants.change-status');
        Route::post('tenants/{id}/push-reminders', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'pushReminders'])->name('landlord.tenants.push-reminders');
        Route::post('tenants/{id}/verify', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'verify'])->name('landlord.tenants.verify');
        Route::delete('tenants/{id}/verify', [\App\Http\Controllers\Api\V1\Landlord\TenantController::class, 'unverify'])->name('landlord.tenants.unverify');

        // Tenant wallet configuration (Phase 54/55)
        Route::get('tenants/{id}/wallet', [\App\Http\Controllers\Api\V1\Landlord\TenantWalletController::class, 'show'])->name('landlord.tenants.wallet.show');
        Route::put('tenants/{id}/wallet', [\App\Http\Controllers\Api\V1\Landlord\TenantWalletController::class, 'upsert'])->name('landlord.tenants.wallet.upsert');
        Route::delete('tenants/{id}/wallet', [\App\Http\Controllers\Api\V1\Landlord\TenantWalletController::class, 'destroy'])->name('landlord.tenants.wallet.destroy');

        // Platform statistics
        Route::get('stats', [\App\Http\Controllers\Api\V1\Landlord\StatsController::class, 'index'])->name('landlord.stats');

        // Plan configuration CMS
        Route::get('plan-configs', [PlanConfigController::class, 'index'])->name('plan-configs.index');
        Route::get('plan-configs/{plan}', [PlanConfigController::class, 'show'])->name('plan-configs.show');
        Route::put('plan-configs/{plan}', [PlanConfigController::class, 'update'])->name('plan-configs.update');

        // Support tickets
        Route::get('support/stats', [SupportController::class, 'stats'])->name('support.stats');
        Route::get('support', [SupportController::class, 'index'])->name('support.index');
        Route::get('support/{id}', [SupportController::class, 'show'])->name('support.show');
        Route::post('support/{id}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::patch('support/{id}/status', [SupportController::class, 'updateStatus'])->name('support.status');
        Route::patch('support/{id}/priority', [SupportController::class, 'updatePriority'])->name('support.priority');

        // Platform SMS settings
        Route::get('platform-settings/sms', [PlatformSettingsController::class, 'smsIndex'])->name('platform-settings.sms.index');
        Route::put('platform-settings/sms/{provider}', [PlatformSettingsController::class, 'smsUpdate'])->name('platform-settings.sms.update');
        Route::post('platform-settings/sms/{provider}/activate', [PlatformSettingsController::class, 'smsActivate'])->name('platform-settings.sms.activate');
        Route::post('platform-settings/sms/{provider}/deactivate', [PlatformSettingsController::class, 'smsDeactivate'])->name('platform-settings.sms.deactivate');

        // Platform Email settings
        Route::get('platform-settings/email', [PlatformSettingsController::class, 'emailIndex'])->name('platform-settings.email.index');
        Route::put('platform-settings/email', [PlatformSettingsController::class, 'emailUpdate'])->name('platform-settings.email.update');
        Route::post('platform-settings/email/test', [PlatformSettingsController::class, 'emailTest'])->name('platform-settings.email.test');

        // Platform Branding
        Route::get('platform-settings/branding', [PlatformSettingsController::class, 'brandingIndex'])->name('platform-settings.branding.index');
        Route::put('platform-settings/branding', [PlatformSettingsController::class, 'brandingUpdate'])->name('platform-settings.branding.update');
        Route::post('platform-settings/branding/logo', [PlatformSettingsController::class, 'brandingUploadLogo'])->name('platform-settings.branding.logo');
        Route::post('platform-settings/branding/favicon', [PlatformSettingsController::class, 'brandingUploadFavicon'])->name('platform-settings.branding.favicon');
        Route::delete('platform-settings/branding/logo', [PlatformSettingsController::class, 'brandingDeleteLogo'])->name('platform-settings.branding.logo.delete');
        Route::delete('platform-settings/branding/favicon', [PlatformSettingsController::class, 'brandingDeleteFavicon'])->name('platform-settings.branding.favicon.delete');

        // Billing gateway settings
        Route::get('billing-settings', [BillingSettingsController::class, 'index'])->name('billing-settings.index');
        Route::put('billing-settings/{gateway}', [BillingSettingsController::class, 'update'])->name('billing-settings.update');
        Route::post('billing-settings/{gateway}/activate', [BillingSettingsController::class, 'activate'])->name('billing-settings.activate');
        Route::post('billing-settings/{gateway}/deactivate', [BillingSettingsController::class, 'deactivate'])->name('billing-settings.deactivate');

        // Featured Repo Items (landlord manual curation + payment confirmation)
        Route::get('featured-items', [\App\Http\Controllers\Api\V1\Landlord\FeaturedItemController::class, 'index'])->name('landlord.featured-items.index');
        Route::post('featured-items', [\App\Http\Controllers\Api\V1\Landlord\FeaturedItemController::class, 'store'])->name('landlord.featured-items.store');
        Route::delete('featured-items/{id}', [\App\Http\Controllers\Api\V1\Landlord\FeaturedItemController::class, 'destroy'])->name('landlord.featured-items.destroy');
        Route::post('featured-items/{id}/confirm-payment', [\App\Http\Controllers\Api\V1\Landlord\FeaturedItemController::class, 'confirmPayment'])->name('landlord.featured-items.confirm-payment');

        // Hot Deals (landlord oversight)
        Route::get('hot-deals', [\App\Http\Controllers\Api\V1\Landlord\FeaturedItemController::class, 'hotDeals'])->name('landlord.hot-deals.index');
        Route::delete('hot-deals/{id}', [\App\Http\Controllers\Api\V1\Landlord\FeaturedItemController::class, 'destroyDeal'])->name('landlord.hot-deals.destroy');
    });
});
