<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\LandlordUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends BaseApiController
{
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'landlord-login:'.Str::lower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->error("Too many login attempts. Try again in {$seconds} seconds.", 429);
        }

        $user = LandlordUser::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_MINUTES * 60);

            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->is_active) {
            return $this->error('Account deactivated.', 403);
        }

        RateLimiter::clear($throttleKey);

        // 2FA is mandatory for landlord users
        if (! $user->has2faEnabled()) {
            // First-time setup — issue a setup token
            $setupToken = $user->createToken('2fa-setup', ['2fa-setup'], now()->addMinutes(30))->plainTextToken;

            return response()->json([
                'success'      => true,
                'requires_2fa_setup' => true,
                'setup_token'  => $setupToken,
                'message'      => '2FA is required. Please set up your authenticator.',
            ]);
        }

        $preAuthToken = $user->createToken('2fa-pre-auth', ['2fa-challenge'], now()->addMinutes(10))->plainTextToken;

        return response()->json([
            'success'        => true,
            'two_factor'     => true,
            'pre_auth_token' => $preAuthToken,
            'message'        => 'Enter your 2FA code to complete login.',
        ]);
    }

    public function setup2fa(Request $request): JsonResponse
    {
        /** @var LandlordUser $user */
        $user      = $request->user();
        $google2fa = new Google2FA;
        $secret    = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpauthUrl = $google2fa->getQRCodeUrl('LENDR', $user->email, $secret);

        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $svg      = (new Writer($renderer))->writeString($otpauthUrl);
        $qrCode   = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return $this->success([
            'secret'      => $secret,
            'qr_code_url' => $qrCode,
        ], 'Scan QR code, then call /landlord/auth/2fa/verify.');
    }

    public function verify2fa(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        /** @var LandlordUser $user */
        $user  = $request->user();
        $valid = (new Google2FA)->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            return $this->error('Invalid code.', 422);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        // Revoke setup token, issue full-access token
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('landlord-session')->plainTextToken;

        $user->updateQuietly(['last_login_at' => now()]);

        return $this->success([
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 'Login successful.');
    }

    public function challenge2fa(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        /** @var LandlordUser $user */
        $user  = $request->user();
        $valid = (new Google2FA)->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            return $this->error('Invalid code.', 422);
        }

        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('landlord-session')->plainTextToken;

        $user->updateQuietly(['last_login_at' => now()]);

        return $this->success([
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user()->only('id', 'name', 'email', 'last_login_at'));
    }
}
