<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Mail\StaffInvitationMail;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use App\Services\PlanFeatureService;
use App\Traits\RunsInTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Landlord view of one tenant and its staff accounts. Everything but the tenant
 * record itself lives in the tenant's own database.
 */
class TenantUserController extends BaseApiController
{
    use RunsInTenantContext;

    /**
     * GET /v1/landlord/tenants/{id}/overview
     */
    public function overview(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        $stats = $this->inTenant($tenant, function () {
            $openLoans = Loan::whereIn('status', [LoanStatus::Disbursed, LoanStatus::Active]);

            return [
                'staff_total' => User::count(),
                'staff_active' => User::where('is_active', true)->count(),
                'borrowers' => Borrower::count(),
                'active_loans' => (clone $openLoans)->count(),
                'outstanding_balance' => number_format((float) $openLoans->sum('outstanding_balance'), 2, '.', ''),
            ];
        });

        return $this->success([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'currency' => $tenant->currency,
                'timezone' => $tenant->timezone,
                'admin_email' => $tenant->admin_email,
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'created_at' => $tenant->created_at?->toDateTimeString(),
                'domains' => $tenant->domains->pluck('domain'),
                'login_url' => $tenant->loginUrl(),
            ],
            'stats' => $stats,
            'roles' => array_map(
                fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
        ]);
    }

    /**
     * GET /v1/landlord/tenants/{id}/users
     */
    public function index(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->success($this->inTenant($tenant, fn () => User::orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->format($user))));
    }

    /**
     * POST /v1/landlord/tenants/{id}/users — invites them by email, as the tenant's own staff screen does.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->inTenant($tenant, function () use ($request, $tenant) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:20'],
                'role' => ['required', Rule::enum(UserRole::class)],
            ]);

            if (! PlanFeatureService::forTenant()->canAddUser(User::count())) {
                return $this->error("This workspace has reached its plan's staff limit. Upgrade the plan to add more users.", 422);
            }

            $user = User::create([
                ...$data,
                'password' => null,
                'is_active' => false,
                'invitation_token' => Str::random(64),
                'invited_at' => now(),
            ]);

            $this->audit($request, "LENDR support invited {$user->name}", $user);

            if (! $this->sendInvitation($tenant, $user)) {
                return $this->success($this->format($user), "User added, but the invitation email failed. Use 'Resend invite' to try again.", 201);
            }

            return $this->success($this->format($user), 'Invitation sent to '.$user->email.'.', 201);
        });
    }

    /**
     * POST /v1/landlord/tenants/{id}/users/{userId}/suspend
     */
    public function suspend(Request $request, string $id, int $userId): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->inTenant($tenant, function () use ($request, $userId) {
            $user = User::findOrFail($userId);

            if ($user->invitation_token) {
                return $this->error("This user hasn't accepted their invitation yet.", 422);
            }

            if (
                $user->is_active
                && $user->isSuperAdmin()
                && User::where('role', UserRole::SuperAdmin)->where('is_active', true)->count() === 1
            ) {
                return $this->error('This is the only active Super Admin. Suspend the whole workspace instead.', 422);
            }

            $user->update(['is_active' => false]);
            $user->tokens()->delete();

            $this->audit($request, "LENDR support suspended {$user->name}", $user);

            return $this->success($this->format($user), 'User suspended.');
        });
    }

    /**
     * POST /v1/landlord/tenants/{id}/users/{userId}/activate
     */
    public function activate(Request $request, string $id, int $userId): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->inTenant($tenant, function () use ($request, $userId) {
            $user = User::findOrFail($userId);

            if ($user->invitation_token) {
                return $this->error("This user hasn't accepted their invitation yet.", 422);
            }

            $user->update(['is_active' => true]);

            $this->audit($request, "LENDR support reactivated {$user->name}", $user);

            return $this->success($this->format($user), 'User reactivated.');
        });
    }

    /**
     * POST /v1/landlord/tenants/{id}/users/{userId}/resend-invite
     */
    public function resendInvite(string $id, int $userId): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->inTenant($tenant, function () use ($tenant, $userId) {
            $user = User::findOrFail($userId);

            if (! $user->invitation_token) {
                return $this->error('This user has already accepted their invitation.', 422);
            }

            if (! $this->sendInvitation($tenant, $user)) {
                return $this->error('The invitation email could not be sent. Check Platform Settings → Email.', 502);
            }

            return $this->success($this->format($user), 'Invitation resent to '.$user->email.'.');
        });
    }

    private function sendInvitation(Tenant $tenant, User $user): bool
    {
        $url = route('invitation.show', ['tenant' => $tenant->id, 'token' => $user->invitation_token]);

        try {
            (new TenantMailService)->send($user->email, new StaffInvitationMail($user, $url, $tenant->name));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Recorded in the tenant's own audit log, so they can see what support changed. */
    private function audit(Request $request, string $description, User $user): void
    {
        activity('support')
            ->performedOn($user)
            ->withProperties(['landlord' => $request->user()->email])
            ->log($description);
    }

    private function format(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->value,
            'role_label' => $user->role?->label(),
            'status' => match (true) {
                (bool) $user->invitation_token => 'invited',
                $user->is_active => 'active',
                default => 'suspended',
            },
            'two_factor' => $user->hasRole2faEnabled(),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
