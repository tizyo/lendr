<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\StaffLoginRequest;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class StaffAuthController extends BaseApiController
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    // ─── Login ────────────────────────────────────────────────────────────────

    public function login(StaffLoginRequest $request): JsonResponse
    {
        $login = $request->input('login');
        $password = $request->input('password');
        $device = $request->input('device', $request->userAgent() ?? 'api');
        $throttleKey = $this->throttleKey($login, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->error("Too many login attempts. Try again in {$seconds} seconds.", 429);
        }

        $user = User::where('email', $login)->orWhere('username', $login)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_MINUTES * 60);

            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->is_active) {
            return $this->error('Your account is deactivated. Contact your administrator.', 403);
        }

        RateLimiter::clear($throttleKey);

        // 2FA required — issue short-lived pre-auth token for the challenge step
        if ($user->hasRole2faEnabled()) {
            $preAuthToken = $user->createToken(
                '2fa-pre-auth',
                ['2fa-challenge'],
                now()->addMinutes(10),
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'two_factor' => true,
                'pre_auth_token' => $preAuthToken,
                'message' => 'Two-factor authentication required.',
            ]);
        }

        $token = $user->createToken($device)->plainTextToken;
        $user->updateQuietly(['last_login_at' => now()]);

        return $this->success([
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 'Login successful.');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    // ─── Refresh ──────────────────────────────────────────────────────────────

    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $request->input('device', $request->userAgent() ?? 'api');

        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken($device)->plainTextToken;

        return $this->success(['token' => $token], 'Token refreshed.');
    }

    // ─── Current User ─────────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('roles', 'permissions');

        return $this->success($this->formatUser($user, true));
    }

    // ─── 2FA Setup ────────────────────────────────────────────────────────────

    public function setup2fa(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ], 'Scan the QR code with your authenticator app, then confirm with /2fa/verify.');
    }

    // ─── 2FA Verify (confirm setup) ───────────────────────────────────────────

    public function verify2fa(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return $this->error('2FA not set up. Call /auth/2fa/setup first.', 422);
        }

        $valid = (new Google2FA)->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            return $this->error('Invalid authentication code.', 422);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->success(null, '2FA enabled successfully.');
    }

    // ─── 2FA Disable ──────────────────────────────────────────────────────────

    public function disable2fa(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $this->success(null, '2FA disabled.');
    }

    // ─── 2FA Challenge (during login flow) ────────────────────────────────────

    public function challenge2fa(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->hasRole2faEnabled()) {
            return $this->error('2FA is not enabled on this account.', 422);
        }

        $valid = (new Google2FA)->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            return $this->error('Invalid authentication code.', 422);
        }

        // Revoke pre-auth token → issue full-access token
        $request->user()->currentAccessToken()->delete();
        $device = $request->input('device', $request->userAgent() ?? 'api');
        $token = $user->createToken($device)->plainTextToken;

        $user->updateQuietly(['last_login_at' => now()]);

        return $this->success([
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 'Login successful.');
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error(__($status), 422);
        }

        return $this->success(null, 'Password reset link sent to your email.');
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'force_password_reset' => false,
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 422);
        }

        return $this->success(null, 'Password reset. Please log in with your new password.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function throttleKey(string $login, string $ip): string
    {
        return 'api-login:'.Str::lower($login).'|'.$ip;
    }

    private function formatUser(User $user, bool $withPermissions = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'role' => $user->role?->value,
            'department' => $user->department,
            'avatar_url' => $user->avatar_url,
            'is_active' => $user->is_active,
            'two_factor' => $user->hasRole2faEnabled(),
        ];

        if ($withPermissions) {
            $data['roles'] = $user->getRoleNames();
            $data['permissions'] = $user->getAllPermissions()->pluck('name');
        }

        return $data;
    }
}
