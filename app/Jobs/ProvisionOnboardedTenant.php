<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\TenantVerificationMail;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Events\TenantCreated;

/**
 * Builds a self-service signup's workspace once the browser has its answer.
 *
 * Creating and migrating a tenant database takes longer than the web server lets a
 * request run (30s), and a request killed halfway left a half-built workspace that
 * still held the chosen name. Run with dispatchAfterResponse(): PHP-FPM stops timing
 * a request once its response is sent.
 */
class ProvisionOnboardedTenant
{
    use Dispatchable;

    /** @param  array{name: string, email: string, phone: ?string, password: string}  $admin  password already hashed */
    public function __construct(
        public string $tenantId,
        public array $admin,
        public string $verificationUrl,
    ) {}

    public function handle(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $tenant = Tenant::findOrFail($this->tenantId);

        try {
            // The database, migrations and seed pipeline from TenancyServiceProvider.
            event(new TenantCreated($tenant));

            $tenant->run(fn () => User::withoutEvents(fn () => User::create([
                ...$this->admin,
                'role' => UserRole::SuperAdmin,
                'is_active' => false, // activated after email verification
            ])));
        } catch (\Throwable $e) {
            tenancy()->end();
            report($e);
            $this->discard($tenant);

            return;
        }

        // Already off the signup request, so send now rather than rely on a queue worker.
        rescue(fn () => Mail::to($this->admin['email'])->send(new TenantVerificationMail($tenant, $this->verificationUrl)));
    }

    /** Frees the workspace name so the owner can simply sign up again. */
    private function discard(Tenant $tenant): void
    {
        rescue(fn () => $tenant->domains()->delete());
        rescue(fn () => $tenant->delete()); // TenantDeleted drops its database, if it got that far
    }
}
