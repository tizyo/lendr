<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\Staff\StoreStaffRequest;
use App\Http\Requests\Api\V1\Staff\UpdateStaffRequest;
use App\Mail\StaffInvitationMail;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use App\Services\PlanFeatureService;
use App\Traits\GuardsStaffRoleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class StaffController extends BaseApiController
{
    use GuardsStaffRoleAssignment;

    public function index(Request $request): JsonResponse
    {
        $staff = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%");
            }))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->department, fn ($q, $d) => $q->where('department', $d))
            ->when($request->status, fn ($q, $s) => match ($s) {
                'active' => $q->where('is_active', true),
                'inactive' => $q->where('is_active', false),
                default => $q,
            })
            ->when($request->sort,
                fn ($q) => $q->orderBy($request->sort, $request->direction ?? 'asc'),
                fn ($q) => $q->latest(),
            )
            ->paginate($request->per_page ?? 20);

        return $this->paginated($staff, fn (User $u) => $this->formatStaff($u));
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $svc = PlanFeatureService::forTenant();
        if (! $svc->canAddUser(User::count())) {
            return $this->error(
                'Your plan\'s staff user limit has been reached. Upgrade your plan to add more users.',
                403,
            );
        }

        $this->assertCanGrantRole(UserRole::from($request->validated('role')));

        $token = Str::random(64);

        $staff = User::create([
            ...$request->validated(),
            'password' => null,
            'is_active' => false,
            'invitation_token' => $token,
            'invited_at' => now(),
        ]);

        $tenant = tenancy()->tenant;
        $orgName = $tenant?->name ?? config('app.name');

        if ($tenant) {
            $invitationUrl = route('invitation.show', ['tenant' => $tenant->id, 'token' => $token]);
            (new TenantMailService)->send($staff->email, new StaffInvitationMail($staff, $invitationUrl, $orgName));
        }

        return $this->success($this->formatStaff($staff), 'Invitation sent to '.$staff->email.'.', 201);
    }

    public function show(User $staff): JsonResponse
    {
        $staff->load('roles', 'permissions');

        return $this->success([
            ...$this->formatStaff($staff, true),
            'roles' => $staff->getRoleNames(),
            'permissions' => $staff->getAllPermissions()->pluck('name'),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('role', $data)) {
            $newRole = UserRole::from($data['role']);

            if ($staff->role !== $newRole) {
                $this->assertCanGrantRole($newRole);
            }
        }

        $staff->update($data);

        return $this->success($this->formatStaff($staff), 'Staff member updated.');
    }

    public function destroy(User $staff): JsonResponse
    {
        if ($staff->id === auth()->id()) {
            return $this->error('You cannot delete your own account.', 422);
        }

        if ($staff->createdLoans()->whereIn('status', ['disbursed', 'active'])->exists()) {
            return $this->error('Cannot delete staff with active loans assigned.', 422);
        }

        $staff->delete();

        return $this->success(null, 'Staff member removed.');
    }

    public function resetPassword(User $staff): JsonResponse
    {
        $status = Password::sendResetLink(['email' => $staff->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error('Failed to send reset email. Check SMTP settings.', 500);
        }

        return $this->success(null, 'Password reset email sent.');
    }

    public function toggleStatus(User $staff): JsonResponse
    {
        if ($staff->id === auth()->id()) {
            return $this->error('You cannot deactivate your own account.', 422);
        }

        $staff->update(['is_active' => ! $staff->is_active]);
        $action = $staff->fresh()->is_active ? 'activated' : 'deactivated';

        return $this->success(['is_active' => $staff->is_active], "Staff account {$action}.");
    }

    public function activity(User $staff): JsonResponse
    {
        $logs = Activity::causedBy($staff)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->description,
                'subject_type' => class_basename($log->subject_type ?? ''),
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at->format('d M Y H:i'),
            ]);

        return $this->success($logs);
    }

    private function formatStaff(User $user, bool $full = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'role' => $user->role?->value,
            'department' => $user->department,
            'avatar_url' => $user->avatar_url,
            'is_active' => $user->is_active,
            'two_factor' => $user->hasRole2faEnabled(),
            'created_at' => $user->created_at?->format('d M Y'),
        ];

        if ($full) {
            $data['last_login_at'] = $user->last_login_at?->format('d M Y H:i');
        }

        return $data;
    }
}
