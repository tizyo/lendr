<?php

use Knuckles\Scribe\Extracting\Strategies;

return [
    /*
     * The HTML <title> for the generated docs.
     */
    'title' => 'LENDR API Documentation',

    'description' => 'REST API for the LENDR multi-tenant loan management platform. '.
                     'All endpoints require a Bearer token obtained via POST /api/v1/auth/login, '.
                     'except where noted as public.',

    'base_url' => env('APP_URL', 'https://app.lendr.app'),

    /*
     * Docs are generated at: public/docs/
     * Browsable at:          /docs
     */
    'type' => 'laravel',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'middleware' => ['web'],
    ],

    'auth' => [
        'enabled' => true,
        'in' => 'bearer',
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_KEY', ''),
        'placeholder' => '{STAFF_TOKEN}',
        'extra_info' => 'Obtain a token via **POST /api/v1/auth/login**. Include it in the Authorization header as `Bearer {token}`.',
    ],

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [
                'api/v1/auth/refresh',
                'telescope/*',
                'horizon/*',
            ],
        ],
    ],

    'examples' => [
        'faker_seed' => 2026,
        'models_source' => ['factoryCreate', 'factoryMake', 'database'],
    ],

    'strategies' => [
        'metadata' => [
            Strategies\Metadata\GetFromDocBlocks::class,
            Strategies\Metadata\GetFromMetadataAttributes::class,
        ],
        'urlParameters' => [
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromUrlParamAttribute::class,
            Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            Strategies\QueryParameters\GetFromFormRequest::class,
            Strategies\QueryParameters\GetFromInlineValidator::class,
            Strategies\QueryParameters\GetFromQueryParamAttribute::class,
            Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            Strategies\Headers\GetFromHeaderAttribute::class,
            Strategies\Headers\GetFromHeaderTag::class,
        ],
        'bodyParameters' => [
            Strategies\BodyParameters\GetFromFormRequest::class,
            Strategies\BodyParameters\GetFromInlineValidator::class,
            Strategies\BodyParameters\GetFromBodyParamAttribute::class,
            Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            Strategies\Responses\UseResponseAttributes::class,
            Strategies\Responses\UseTransformerTags::class,
            Strategies\Responses\UseApiResourceTags::class,
            Strategies\Responses\UseResponseTag::class,
            Strategies\Responses\UseResponseFileTag::class,
        ],
        'responseFields' => [
            Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
            Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    'postman' => [
        'enabled' => true,
        'overridden_values' => [],
    ],

    'openapi' => [
        'enabled' => true,
        'overridden_values' => [],
    ],

    'groups' => [
        'default' => 'Endpoints',
        'order' => [
            'Authentication',
            'Borrowers',
            'Loans',
            'Loan Types',
            'Payments',
            'Reports',
            'Dashboard',
            'Settings',
            'Staff',
            'Marketplace',
        ],
    ],

    'logo' => false,

    'last_updated' => 'auto',

    'example_languages' => ['bash', 'javascript', 'php'],

    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        'serializer' => null,
    ],
];
