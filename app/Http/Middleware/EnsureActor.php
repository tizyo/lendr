<?php

namespace App\Http\Middleware;

use App\Models\Landlord\GhostUser;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who a route is for, checked after auth:sanctum.
 *
 * auth:sanctum accepts any valid token whatever model owns it, so on its own a
 * borrower's app token or a repo-marketplace (GhostUser) token passed every staff
 * API route. Each audience's routes say which account type they serve:
 *
 *   actor:staff     workspace staff (Tenant\User), session or token
 *   actor:borrower  borrower app tokens (Tenant\Borrower)
 *   actor:ghost     repo-marketplace accounts (Landlord\GhostUser)
 */
class EnsureActor
{
    private const TYPES = [
        'staff' => User::class,
        'borrower' => Borrower::class,
        'ghost' => GhostUser::class,
    ];

    public function handle(Request $request, Closure $next, string $type): Response
    {
        $class = self::TYPES[$type] ?? throw new \InvalidArgumentException("Unknown actor type [{$type}].");

        abort_unless($request->user() instanceof $class, 403, 'This account cannot use this endpoint.');

        return $next($request);
    }
}
