<?php

namespace App\Http\Middleware;

use App\Models\Landlord\ImpersonationSession;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a landlord's "sign in as" session inside its limits: the central record
 * decides whether it is still live (time limit, ended from the panel), it stays
 * on the tenant it was started for, and it can't touch the user's credentials.
 */
class EnforceImpersonationSession
{
    private const CREDENTIAL_ROUTES = [
        'staff.profile.password',
        'api.v1.auth.refresh',
        'api.v1.auth.2fa.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->hasSession() ? $request->session()->get(ImpersonationSession::SESSION_KEY) : null;

        // The landlord panel shares a host and cookie with shared-portal tenants.
        if (! $id || $request->is('landlord', 'landlord/*')) {
            return $next($request);
        }

        $session = ImpersonationSession::with('tenant')->find($id);

        if (! $session?->isLive() || ! $session->tenant?->servesStaffHost($request->getHost())) {
            return $this->signOut($request, $session);
        }

        if ($request->routeIs(...self::CREDENTIAL_ROUTES)) {
            $message = "You can't change this user's password or 2FA while signed in as them.";

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 403)
                : back()->with('error', $message);
        }

        if ($request->routeIs('logout', 'portal.logout')) {
            $session->end('logout');
        }

        $request->attributes->set(ImpersonationSession::REQUEST_ATTRIBUTE, $session);

        return $next($request);
    }

    private function signOut(Request $request, ?ImpersonationSession $session): Response
    {
        $session?->end('expired');

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'This support session has ended.'], 401);
        }

        return Inertia::location($session?->tenant?->loginUrl() ?? url('/'));
    }
}
