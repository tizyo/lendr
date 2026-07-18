<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    /**
     * GET /invitation/{tenant}/{token}
     * Show the set-password form for an invited staff member.
     */
    public function show(string $tenantId, string $token): Response|RedirectResponse
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return redirect()->route('onboarding')->withErrors(['token' => 'Invalid invitation link.']);
        }

        tenancy()->initialize($tenant);

        $user = User::where('invitation_token', $token)
            ->where('is_active', false)
            ->first();

        tenancy()->end();

        if (! $user) {
            return redirect()->route('onboarding')->withErrors(['token' => 'This invitation link is invalid or has already been used.']);
        }

        return Inertia::render('auth/AcceptInvitation', [
            'tenantId' => $tenantId,
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
            'orgName' => $tenant->name,
        ]);
    }

    /**
     * POST /invitation/{tenant}/{token}
     * Activate the account with the chosen password and log the user in.
     */
    public function accept(Request $request, string $tenantId, string $token): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return back()->withErrors(['token' => 'Invalid invitation link.']);
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::where('invitation_token', $token)
                ->where('is_active', false)
                ->firstOrFail();

            $user->update([
                'password' => $request->password,
                'is_active' => true,
                'invitation_token' => null,
                'invited_at' => null,
                'email_verified_at' => now(),
                'force_password_reset' => false,
            ]);

            Auth::login($user);
            $request->session()->regenerate();

        } finally {
            // Do not end tenancy — the user is now logged in and in tenant context.
        }

        return redirect()->route('dashboard')->with('success', 'Welcome! Your account is now active.');
    }
}
