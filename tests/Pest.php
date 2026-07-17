<?php

use Tests\Traits\RunsTenantMigrations;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/
uses(
    Tests\TestCase::class,
    RunsTenantMigrations::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/
expect()->extend('toBeZmwAmount', function () {
    return $this->toMatch('/^\d+\.\d{2}$/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/
function actingAsAdmin(): \App\Models\Tenant\User
{
    $user = \App\Models\Tenant\User::factory()->create([
        'role'      => \App\Enums\UserRole::SuperAdmin,
        'is_active' => true,
    ]);

    \Spatie\Permission\Models\Permission::all()->each(
        fn ($perm) => \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm->name])
    );

    return $user;
}
