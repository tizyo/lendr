<?php

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        // Admin web (Inertia — session-based)
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Staff API (Sanctum token-based)
        'api' => [
            'driver'   => 'token',
            'provider' => 'users',
        ],

        // Borrower API (Sanctum token-based)
        'borrower' => [
            'driver'   => 'token',
            'provider' => 'borrowers',
        ],

        // Landlord / Super Admin (central DB)
        'landlord' => [
            'driver'   => 'token',
            'provider' => 'landlord_users',
        ],
    ],

    'providers' => [
        // Tenant staff — DB connection switched by tenancy middleware
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Tenant\User::class,
        ],

        // Tenant borrowers
        'borrowers' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Tenant\Borrower::class,
        ],

        // Central landlord users
        'landlord_users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Landlord\LandlordUser::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
