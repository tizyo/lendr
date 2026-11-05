<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Mail\StaffInvitationMail;
use App\Mail\TenantWelcomeMail;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use App\Services\PlanFeatureService;
use App\Traits\RunsInTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

            // Before the login-tracking migration has run on this workspace there's nothing to show.
            $lastLogin = rescue(fn () => User::whereNotNull('last_login_at')->orderByDesc('last_login_at')->first(['id', 'name', 'last_login_at']), null, false);
            $lastSeen = rescue(fn () => User::max('last_seen_at'), null, false);

            return [
                'last_login' => $lastLogin ? [
                    'user_id' => $lastLogin->id,
                    'name' => $lastLogin->name,
                    'at' => $lastLogin->last_login_at->toIso8601String(),
                ] : null,
                'last_seen_at' => $lastSeen ? \Illuminate\Support\Carbon::parse($lastSeen)->toIso8601String() : null,
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
                'email_verified_at' => $tenant->email_verified_at?->toDateTimeString(),
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
     * POST /v1/landlord/tenants/{id}/verify-email
     * Does what the onboarding verification link does, for owners who never got it.
     */
    public function verifyEmail(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        if ($tenant->isEmailVerified()) {
            return $this->error('This workspace is already verified.', 422);
        }

        $tenant->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ]);

        $this->inTenant($tenant, function () use ($request, $tenant) {
            if ($tenant->admin_email) {
                User::where('email', $tenant->admin_email)->whereNull('invitation_token')->update(['is_active' => true]);
            }

            activity('support')
                ->withProperties(['landlord' => $request->user()->email])
                ->log('LENDR support verified the workspace email');
        });

        // A mail outage must never block activation — the workspace is already live.
        if ($tenant->admin_email) {
            rescue(fn () => Mail::to($tenant->admin_email)->queue(new TenantWelcomeMail($tenant, $tenant->loginUrl())));
        }

        return $this->success(
            ['email_verified_at' => $tenant->email_verified_at->toDateTimeString()],
            'Workspace verified. Its staff can now log in.',
        );
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
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
    }

    /**
     * GET /v1/landlord/tenants/{id}/activity?user_id= — what the workspace's staff
     * did: sign-ins, screens opened and records changed, newest first.
     */
    public function activity(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $userId = $request->integer('user_id') ?: null;
        $limit = min(max($request->integer('limit', 60), 10), 200);

        return $this->success($this->inTenant($tenant, function () use ($userId, $limit) {
            $entries = \Spatie\Activitylog\Models\Activity::query()
                ->with('causer:id,name')
                ->where('causer_type', (new User)->getMorphClass())
                ->when($userId, fn ($q) => $q->where('causer_id', $userId))
                ->latest('id')
                ->limit($limit)
                ->get();

            return $entries->map(fn ($entry) => $this->formatActivity($entry))->all();
        }));
    }

    private function formatActivity(\Spatie\Activitylog\Models\Activity $entry): array
    {
        $props = $entry->properties;
        $subject = $entry->subject_type ? Str::headline(class_basename($entry->subject_type)) : null;

        [$kind, $label, $detail] = match ($entry->log_name) {
            'auth' => ['auth', $entry->description, collect([
                ($props['channel'] ?? null) === 'app' ? 'Mobile app' : (($props['channel'] ?? null) === 'web' ? 'Web' : null),
                $props['ip'] ?? null,
            ])->filter()->implode(' · ')],
            'navigation' => ['navigation', $this->screenLabel((string) ($props['route'] ?? $entry->description)), $props['path'] ?? null],
            default => ['change', match ($entry->description) {
                'created', 'updated', 'deleted', 'restored' => Str::ucfirst($entry->description).' '.Str::lower((string) $subject),
                default => Str::ucfirst($entry->description),
            }, $subject && $entry->subject_id ? $subject.' #'.$entry->subject_id : null],
        };

        return [
            'id' => $entry->id,
            'at' => $entry->created_at?->toIso8601String(),
            'user_id' => $entry->causer_id,
            'user_name' => $entry->causer?->name,
            'kind' => $kind,
            'label' => $label,
            'detail' => $detail ?: null,
            // Done by LENDR support while signed in as this user.
            'via_support' => $props['impersonated_by'] ?? null,
        ];
    }

    /** "loans.show" → "Viewed loan details", "settings.index" → "Opened Settings". */
    private function screenLabel(string $route): string
    {
        $parts = array_values(array_filter(explode('.', $route), fn ($p) => $p !== 'portal'));

        if ($parts === [] || $parts === ['dashboard']) {
            return 'Opened Dashboard';
        }

        $action = count($parts) > 1 ? array_pop($parts) : 'index';
        $thing = Str::of(implode(' ', $parts))->replace(['-', '_'], ' ')->squish();

        return match ($action) {
            'index' => 'Opened '.$thing->title(),
            'show' => 'Viewed '.$thing->singular()->lower().' details',
            'create' => 'Opened the new '.$thing->singular()->lower().' form',
            'edit' => 'Opened the '.$thing->singular()->lower().' edit form', // the save itself is a "change" entry
            default => 'Opened '.$thing->title().' · '.Str::of($action)->replace(['-', '_'], ' ')->title(),
        };
    }
}
