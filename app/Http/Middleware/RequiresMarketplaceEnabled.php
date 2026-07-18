<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate all marketplace endpoints behind the `marketplace_enabled` feature flag.
 *
 * If the flag is false (or absent), API routes return 503 JSON.
 * Inertia routes redirect to dashboard with a flash message.
 */
class RequiresMarketplaceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = DB::table('settings')
            ->where('key', 'marketplace_enabled')
            ->value('value');

        if (! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The Marketplace is coming soon. This feature is not yet enabled for your plan.',
                    'code' => 'MARKETPLACE_DISABLED',
                ], 503);
            }

            return redirect()->route('dashboard')->with(
                'error',
                'The Marketplace is coming soon and is not yet enabled for your account.',
            );
        }

        return $next($request);
    }
}
