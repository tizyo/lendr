<?php

namespace App\Providers;

use App\Models\Landlord\PlatformBranding;
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
        // Inject platform branding into all email + PDF views.
        View::composer(['emails.*', 'pdf.*'], function ($view) {
            try {
                $view->with('branding', PlatformBranding::defaults());
            } catch (\Throwable) {
                $view->with('branding', [
                    'company_name'   => config('app.name', 'LENDR'),
                    'tagline'        => null,
                    'address'        => null,
                    'phone'          => null,
                    'email'          => null,
                    'website'        => null,
                    'logo_url'       => null,
                    'favicon_url'    => null,
                    'primary_color'  => '#059669',
                    'invoice_footer' => null,
                    'email_footer'   => null,
                ]);
            }
        });
    }
}
