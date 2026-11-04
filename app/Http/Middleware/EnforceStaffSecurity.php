<?php

namespace App\Http\Middleware;

use App\Models\Landlord\ImpersonationSession;
use App\Models\Tenant\User;
use App\Support\TenantSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The workspace's Settings → Security rules, which used to be saved but never
 * applied, plus the forced password change:
 *
 *   - force_password_reset (VOZARA imports, admin resets) or a password older than
 *     password_expiry_days → choose a new password (My Profile) before anything else;
 *     login used to send these users to a route that didn't exist;
 *   - require_2fa → set up two-factor sign-in (My Profile) before anything else;
 *   - session_timeout_minutes → a browser session idle that long is signed out.
 *
 * A landlord support session ("sign in as") is never held up by the user's own setup.
 */
class EnforceStaffSecurity
{
    /** Routes a held-up user can still reach: their profile, its 2FA and password steps, sign out. */
    private const ALLOWED = [
        'staff.profile', 'staff.profile.update', 'staff.profile.password',
        'logout', 'portal.logout', 'impersonation.stop',
        'api.v1.auth.me', 'api.v1.auth.logout', 'api.v1.auth.2fa.*',
        'banners.dismiss',
    ];

    public const ACTIVITY_KEY = 'staff_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $request->attributes->get(ImpersonationSession::REQUEST_ATTRIBUTE)) {
            return $next($request);
        }

        if ($timedOut = $this->idleTimeout($request)) {
            return $timedOut;
        }

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($this->mustChangePassword($user)) {
            return $this->hold($request, 'Choose a new password to continue (My Profile → Change Password).');
        }

        if (filter_var(TenantSettings::get('require_2fa'), FILTER_VALIDATE_BOOLEAN) && ! $user->hasRole2faEnabled()) {
            return $this->hold($request, 'Your workspace requires two-factor sign-in. Set it up to continue (My Profile → Two-Factor Authentication).');
        }

        return $next($request);
    }

    private function mustChangePassword(User $user): bool
    {
        if ($user->force_password_reset) {
            return true;
        }

        $days = (int) TenantSettings::get('password_expiry_days', 0);
        if ($days <= 0) {
            return false;
        }

        $changed = $user->password_changed_at ?? $user->created_at;

        return $changed !== null && $changed->lt(now()->subDays($days));
    }

    /** Signs out a browser session idle for longer than the workspace allows. */
    private function idleTimeout(Request $request): ?Response
    {
        if (! $request->hasSession() || ! $request->session()->has(Auth::guard('web')->getName())) {
            return null;
        }

        $minutes = (int) TenantSettings::get('session_timeout_minutes', 0);
        $last = (int) $request->session()->get(self::ACTIVITY_KEY, 0);

        if ($minutes > 0 && $last > 0 && now()->timestamp - $last > $minutes * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'You were signed out after '.$minutes.' minutes without activity.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            // Starter/Trial workspaces sign in on the shared portal, the rest on their own subdomain.
            $portal = in_array($request->getHost(), config('tenancy.central_domains', []), true);

            return redirect()->route($portal ? 'portal.login' : 'login')->with('warning', $message);
        }

        $request->session()->put(self::ACTIVITY_KEY, now()->timestamp);

        return null;
    }

    private function hold(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('staff.profile')->with('warning', $message);
    }
}
