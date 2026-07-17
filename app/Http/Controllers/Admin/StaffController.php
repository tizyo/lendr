<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\StaffInvitationMail;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use App\Services\PlanFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
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
                'active'   => $q->where('is_active', true),
                'inactive' => $q->where('is_active', false),
                default    => $q,
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'username'   => $u->username,
                'phone'      => $u->phone,
                'role'       => $u->role?->value,
                'department' => $u->department,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at->format('d M Y'),
            ]);

        return Inertia::render('staff/Index', [
            'staff'   => $staff,
            'filters' => $request->only(['search', 'role', 'department', 'status']),
            'roles'   => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $svc = PlanFeatureService::forTenant();
        $currentCount = User::count();

        if (! $svc->canAddUser($currentCount)) {
            return back()->with('error',
                "Your plan allows a maximum of {$svc->limitLabel('max_users')} staff members. Upgrade to add more."
            );
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'username'   => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'role'       => ['required', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $token = Str::random(64);

        $staff = User::create([
            ...$data,
            'password'         => null,
            'is_active'        => false,
            'invitation_token' => $token,
            'invited_at'       => now(),
        ]);

        $tenant  = tenancy()->tenant;
        $orgName = $tenant?->name ?? config('app.name');

        // Build invitation URL — uses central domain route so it works for both
        // shared-portal and custom-subdomain tenants.
        if ($tenant) {
            $invitationUrl = route('invitation.show', ['tenant' => $tenant->id, 'token' => $token]);
            (new TenantMailService)->send($staff->email, new StaffInvitationMail($staff, $invitationUrl, $orgName));
        }

        return back()->with('success', 'Invitation sent to ' . $staff->email . '.');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', "unique:users,email,{$staff->id}"],
            'username'   => ['nullable', 'string', 'max:50', "unique:users,username,{$staff->id}"],
            'phone'      => ['nullable', 'string', 'max:20'],
            'role'       => ['required', 'string'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $staff->update($data);

        return back()->with('success', 'Staff member updated.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        if ($staff->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $staff->delete();

        return redirect()->route('staff.index')->with('success', 'Staff member removed.');
    }

    public function resetPassword(User $staff): RedirectResponse
    {
        Password::sendResetLink(['email' => $staff->email]);

        return back()->with('success', 'Password reset email sent.');
    }

    public function toggleStatus(User $staff): RedirectResponse
    {
        if ($staff->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $staff->update(['is_active' => ! $staff->is_active]);

        $action = $staff->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Staff account {$action}.");
    }
}
