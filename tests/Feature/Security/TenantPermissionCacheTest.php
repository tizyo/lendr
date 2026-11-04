<?php

use App\Models\Landlord\Tenant;
use Spatie\Permission\PermissionRegistrar;

/*
 * Each workspace has its own roles and permissions, but spatie's registrar is a
 * singleton built before tenancy: it used one cache key for every workspace, so
 * one tenant's permission map could be served to another.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->where('id', 'like', 'permcache-%')->each(fn ($t) => $t->delete()));
});

it('keys the permission cache by workspace', function () {
    config(['tenancy.bootstrappers' => []]);
    $central = config('permission.cache.key');

    $a = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'permcache-a', 'name' => 'A', 'slug' => 'permcache-a', 'plan' => 'starter', 'status' => 'active']));
    $b = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'permcache-b', 'name' => 'B', 'slug' => 'permcache-b', 'plan' => 'starter', 'status' => 'active']));

    tenancy()->initialize($a);
    $keyA = app(PermissionRegistrar::class)->cacheKey;
    tenancy()->initialize($b);
    $keyB = app(PermissionRegistrar::class)->cacheKey;
    tenancy()->end();

    expect($keyA)->toBe("{$central}.tenant.permcache-a")
        ->and($keyB)->toBe("{$central}.tenant.permcache-b")
        ->and(app(PermissionRegistrar::class)->cacheKey)->toBe($central);
});
