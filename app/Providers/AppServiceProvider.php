<?php

namespace App\Providers;

use App\Mail\Listeners\ApplyPlatformSender;
use App\Mail\Transport\PlatformTransport;
use App\Mail\Transport\ResendTransport;
use App\Models\Landlord\PlatformBranding;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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

        // Inject platform branding into all email + PDF views.
        View::composer(['emails.*', 'pdf.*'], function ($view) {
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
