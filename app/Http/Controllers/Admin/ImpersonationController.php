<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ImpersonationSession;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tenant side of a landlord support session: entering through the one-time
 * link and leaving again. Starting one is Landlord\ImpersonationController.
 */
class ImpersonationController extends Controller
{
    public function enter(Request $request, string $token): RedirectResponse
    {
        $session = ImpersonationSession::pending($token);
        $tenant = $session?->tenant;

        abort_unless($tenant?->servesStaffHost($request->getHost()), 410, 'This support link is invalid or has expired.');

        tenancy()->initialize($tenant);

        $user = User::where('is_active', true)->find($session->target_user_id);

        abort_unless($user && $session->claim(), 410, 'This support link is invalid or has expired.');

        // Shown in the tenant's own audit log, so they can see support was in.
        activity('support')
            ->performedOn($user)
            ->withProperties(['landlord' => $session->landlord_email, 'reason' => $session->reason])
            ->log("LENDR support signed in as {$user->name}");

        // Nothing from a previous login in this browser carries over.
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->put(ImpersonationSession::SESSION_KEY, $session->id);

        if ($tenant->staffUseCentralHost()) {
            $request->session()->put('tenant_id', $tenant->id);
            $request->session()->put('tenant_slug', $tenant->slug);

            return redirect()->route('portal.dashboard');
        }

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): Response
    {
        $id = $request->session()->get(ImpersonationSession::SESSION_KEY);

        if ($id) {
            ImpersonationSession::find($id)?->end('stopped');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(rtrim((string) config('app.url'), '/').'/landlord/tenants');
    }
}
