<?php

namespace App\Http\Middleware;

use App\Models\Landlord\LandlordUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Landlord API gate, used after auth:sanctum.
 *
 * auth:sanctum alone accepts ANY valid token in the central token table —
 * including marketplace (GhostUser) tokens and the landlord's own
 * password-only pre-2FA / setup tokens. This requires:
 *   - the caller to be a LandlordUser, and
 *   - the token to grant the stage being used:
 *       landlord.token:session        full session (issued after 2FA)
 *       landlord.token:2fa-challenge  the pre-auth token from login
 *       landlord.token:2fa-setup      the first-time setup token
 */
class EnsureLandlordToken
{
    public function handle(Request $request, Closure $next, string $stage = 'session'): Response
    {
        $user = $request->user();

        abort_unless($user instanceof LandlordUser, 403, 'Landlord access only.');

        $token = $user->currentAccessToken();

        // A real bearer token must carry the stage's ability. Session tokens carry
        // '*' (all abilities); pre-auth / setup tokens carry only their own.
        if ($token instanceof PersonalAccessToken) {
            $ability = $stage === 'session' ? '*' : $stage;
            $allowed = $stage === 'session'
                ? in_array('*', (array) $token->abilities, true)
                : $token->can($ability);

            abort_unless($allowed, 403, $stage === 'session'
                ? 'Finish signing in with your 2FA code first.'
                : 'This sign-in step has expired. Please log in again.');
        }

        return $next($request);
    }
}
