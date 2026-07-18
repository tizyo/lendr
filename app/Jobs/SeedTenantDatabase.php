<?php

namespace App\Jobs;

use App\Models\Landlord\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;

class SeedTenantDatabase implements ShouldQueue
{
    public function __construct(public Tenant $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            \Artisan::call('db:seed', [
                '--class' => \Database\Seeders\RolesAndPermissionsSeeder::class,
                '--force' => true,
            ]);
        });
    }
}
