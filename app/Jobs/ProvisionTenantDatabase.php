<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\StaffInvitationMail;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

/**
 * Builds the database for a workspace the landlord created (create, migrate, seed
 * roles), after the landlord has their answer: provisioning outlasts the 30s
 * request limit. Then, when one was named, invites the first Super Admin by email,
 * the same invitation the workspace's own staff screen sends.
 *
 * Unlike a self-service signup, a failure keeps the workspace so the landlord can
 * see it and retry with `php artisan tenants:migrate`.
 */
class ProvisionTenantDatabase
{
    use Dispatchable;

    /** @param  array{name: string, email: string}|null  $admin */
    public function __construct(public string $tenantId, public ?array $admin = null) {}

    public function handle(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);

        $tenant = Tenant::findOrFail($this->tenantId);

        try {
            event(new TenantCreated($tenant));
        } catch (\Throwable $e) {
            tenancy()->end();
            report($e);
            Log::error('[Landlord] Workspace database could not be provisioned', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return;
        }

        if (! $this->admin) {
            return;
        }

        rescue(fn () => $tenant->run(function () use ($tenant) {
            $user = User::create([
                'name' => $this->admin['name'],
                'email' => $this->admin['email'],
                'password' => null,
                'role' => UserRole::SuperAdmin,
                'is_active' => false, // activated when the invitation is accepted
                'invitation_token' => Str::random(64),
                'invited_at' => now(),
            ]);

            $url = route('invitation.show', ['tenant' => $tenant->id, 'token' => $user->invitation_token]);

            (new TenantMailService)->send($user->email, new StaffInvitationMail($user, $url, $tenant->name));
        }));
    }
}
