<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    | Sent by App\Http\Middleware\SecurityHeaders on HTML pages (not in local
    | dev, where Vite's dev server serves scripts from another origin).
    |
    | Ships in Report-Only mode: browsers log what the policy WOULD block
    | without blocking it. Check the browser console on the admin app, the
    | borrower PWA and the landlord panel, then set CSP_ENFORCE=true.
    */
    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        'enforce' => (bool) env('CSP_ENFORCE', false),
        'report_uri' => env('CSP_REPORT_URI'),
        // Extra origins (space-separated) for scripts/XHR, e.g. an analytics or payments SDK.
        'extra_script_src' => env('CSP_EXTRA_SCRIPT_SRC', ''),
        'extra_connect_src' => env('CSP_EXTRA_CONNECT_SRC', ''),
    ],

    // Strict-Transport-Security on HTTPS responses outside local dev.
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

];
