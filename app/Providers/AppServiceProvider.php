<?php

namespace App\Providers;

use App\Auth\ActiveUserProvider;
use App\Http\Middleware\InitializeTenancy;
use App\Mail\Listeners\ApplyPlatformSender;
use App\Mail\Transport\PlatformTransport;
use App\Mail\Transport\ResendTransport;
use App\Models\Landlord\ImpersonationSession;
use App\Models\Landlord\PlatformBranding;
use App\Models\Tenant\User;
use App\Services\Mail\EmailBrand;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Sign-in step tokens (anything without the '*' ability) and the only routes
     * each may authenticate on. Route name => required token ability.
     */
    public const STAGE_TOKEN_ROUTES = [
        'api.v1.auth.2fa.challenge' => '2fa-challenge',
        'landlord.auth.2fa.challenge' => '2fa-challenge',
        'landlord.auth.2fa.setup' => '2fa-setup',
        'landlord.auth.2fa.verify' => '2fa-setup',
    ];

    public function register(): void
    {
        // Scoped: fresh per request / queued job, so branding edits reach long-running workers.
        $this->app->scoped(EmailBrand::class);
    }

    public function boot(): void
    {
        // A password-only 2FA pre-auth / setup token authenticates ONLY on the step it
        // was issued for. Without this, every auth:sanctum route (staff, borrower,
        // field, marketplace) accepted it, letting a password skip the 2FA code.
        // A suspended staff member's tokens stop working too (see ActiveUserProvider).
        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid): bool {
            if (! $isValid || ($token->tokenable instanceof User && ! $token->tokenable->is_active)) {
                return false;
            }

            if (in_array('*', (array) $token->abilities, true)) {
                return true;
            }

            $ability = self::STAGE_TOKEN_ROUTES[request()->route()?->getName()] ?? null;

            return $ability !== null && $token->can($ability);
        });

        Auth::provider('active-users', fn ($app, array $config) => new ActiveUserProvider($app['hash'], $config['model']));

        // Every staff login records its tenant, however it happened (see InitializeTenancy).
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'web') {
                session()->put(InitializeTenancy::SESSION_TENANT_KEY, tenancy()->tenant?->id);
            }
        });

        // During a support session the causer is the impersonated staff member, so
        // every audit entry also records which landlord was really at the keyboard.
        Activity::creating(function (Activity $activity) {
            $session = request()->attributes->get(ImpersonationSession::REQUEST_ATTRIBUTE);

            if ($session) {
                $activity->properties = collect($activity->properties)->put('impersonated_by', $session->landlord_email);
            }
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Resend over HTTPS (DigitalOcean blocks SMTP) + the "platform" mailer,
        // which delivers through whatever the landlord saved in Platform Settings.
        Mail::extend('resend', fn (array $config) => new ResendTransport((string) ($config['key'] ?? '')));
        Mail::extend('platform', fn (array $config) => new PlatformTransport(
            $this->app->make('mail.manager'),
            (string) ($config['fallback'] ?? 'log'),
        ));
        Event::listen(MessageSending::class, ApplyPlatformSender::class);

        // Email look: tenant branding in tenant context, platform branding otherwise.
        View::composer(['emails.*', 'components.email.*'], function ($view) {
            $view->with('brand', $this->app->make(EmailBrand::class)->current());
        });

        // Inject platform branding into all PDF views.
        View::composer(['pdf.*'], function ($view) {
            try {
                $view->with('branding', PlatformBranding::defaults());
            } catch (\Throwable) {
                $view->with('branding', [
                    'company_name' => config('app.name', 'LENDR'),
                    'tagline' => null,
                    'address' => null,
                    'phone' => null,
                    'email' => null,
                    'website' => null,
                    'logo_url' => null,
                    'favicon_url' => null,
                    'primary_color' => '#059669',
                    'invoice_footer' => null,
                    'email_footer' => null,
                ]);
            }
        });
    }
}
