<?php

namespace App\Http\Middleware;

use App\Models\Landlord\ImpersonationSession;
use App\Models\Tenant\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records which screens staff open (activity log "navigation"), so the landlord's
 * tenant page can show what someone did after signing in, and keeps the user's
 * last_seen_at fresh. Only successful page loads count; the same screen opened
 * again within half an hour is recorded once. Never fails the request.
 */
class TrackStaffActivity
{
    private const REPEAT_WINDOW_SECONDS = 1800;

    private const SEEN_EVERY_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful() && tenancy()->initialized) {
            rescue(fn () => $this->track($request), report: false);
        }

        return $response;
    }

    private function track(Request $request): void
    {
        $user = $request->user();
        $route = $request->route()?->getName();

        // A page, not the JSON a page loads (those go through /api, but be sure).
        if (! $user instanceof User || ! $route || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
            return;
        }

        $supportSession = $request->session()->has(ImpersonationSession::SESSION_KEY);
        $key = 'staff-nav:'.tenant('id').':'.$user->id;

        if (Cache::get($key) !== $route) {
            Cache::put($key, $route, self::REPEAT_WINDOW_SECONDS);

            activity('navigation')
                ->causedBy($user)
                ->withProperties(['route' => $route, 'path' => '/'.ltrim($request->path(), '/')])
                ->log($route);
        }

        // A support session isn't the user being active.
        if (! $supportSession && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(self::SEEN_EVERY_SECONDS)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }
}
