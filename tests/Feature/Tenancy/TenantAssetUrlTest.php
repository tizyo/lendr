<?php

use App\Models\Landlord\Tenant;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;

/*
 * The app's built JS/CSS must load from the same /build URL whether or not a tenant
 * is active. With stancl's asset() helper tenancy on, @vite pointed every staff page
 * at /tenancy/assets/..., which 500s, and the whole workspace rendered blank.
 * Other tests turn the bootstrappers off, so this one keeps the filesystem one on.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->where('id', 'like', 'assets-%')->each(fn ($t) => $t->delete()));
});

it('keeps asset URLs global while a tenant is active', function () {
    config([
        'tenancy.bootstrappers' => [FilesystemTenancyBootstrapper::class],
        'app.url' => 'https://lendr.test',
    ]);

    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'assets-'.uniqid(),
        'name' => 'Asset Check Finance',
        'slug' => 'assets-'.uniqid(),
        'plan' => 'growth',
        'status' => 'active',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(),
    ]));

    $before = asset('build/assets/app.js');

    tenancy()->initialize($tenant);

    expect(asset('build/assets/app.js'))
        ->toBe($before)
        ->not->toContain('tenancy/assets');
});
