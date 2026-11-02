<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Mail\TenantVerificationMail;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthController extends Controller
{
    /** How long a portal → subdomain sign-in link works. */
    private const HANDOFF_SECONDS = 60;

    // ─── Custom-subdomain login (Growth / Enterprise) ─────────────────────

    public function showLogin(): Response
    {
        return Inertia::render('auth/Login', ['isPortal' => false]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        // Check the workspace before attempting auth.
        if ($tenant = tenancy()->tenant) {
            $this->ensureWorkspaceIsOpen($tenant, 'email');
        }

        $request->authenticate();

        return $this->completeLogin($request);
    }

    /**
     * GET /login/handoff?token=… on a workspace's own subdomain: finishes a sign-in
     * whose password was checked on the shared portal (see portalLogin).
     */
    public function claimHandoff(Request $request): RedirectResponse
    {
        $tenant = tenancy()->tenant;
        $payload = rescue(fn () => json_decode(Crypt::decryptString((string) $request->query('token')), true), null, false);

        $valid = $tenant
            && is_array($payload)
            && ($payload['tenant'] ?? null) === $tenant->id
            && ($payload['expires'] ?? 0) >= now()->timestamp
            && Cache::add('login-handoff:'.($payload['nonce'] ?? ''), true, self::HANDOFF_SECONDS * 2); // one use only

        $user = $valid ? User::find($payload['user'] ?? null) : null;

        if (! $user?->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'That sign-in link has expired. Please sign in here.']);
        }

        try {
            $this->ensureWorkspaceIsOpen($tenant, 'email');
        } catch (ValidationException $e) {
            return redirect()->route('login')->withErrors($e->errors());
        }

        Auth::login($user, (bool) ($payload['remember'] ?? false));

        return $this->completeLogin($request);
    }

    /** Everything after the password is accepted: account state, forced reset, then 2FA. */
    private function completeLogin(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact your administrator.',
            ]);
        }

        if ($user->force_password_reset) {
            return redirect()->route('password.reset.forced');
        }

        if ($user->hasRole2faEnabled()) {
            $request->session()->put('2fa_user_id', $user->id);
            Auth::logout();

            return redirect()->route('2fa.challenge');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function show2faChallenge(): Response
    {
        return Inertia::render('auth/TwoFactorChallenge');
    }

    public function verify2fa(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $userId = $request->session()->get('2fa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);

        $valid = $user->two_factor_secret &&
            (new Google2FA)->verifyKey($user->two_factor_secret, $request->code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        Auth::login($user);
        $request->session()->forget('2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    // ─── Shared-portal login (Starter / Trial — app.localhost / app.lendr.app) ──

    public function showPortalLogin(): Response
    {
        return Inertia::render('auth/Login', ['isPortal' => true]);
    }

    public function portalLogin(Request $request): SymfonyResponse
    {
        $request->validate([
            'workspace' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensurePortalLoginIsNotRateLimited($request);

        $tenant = Tenant::where('slug', $request->workspace)->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'workspace' => 'No workspace found with that name.',
            ]);
        }

        $this->ensureWorkspaceIsOpen($tenant, 'workspace');

        tenancy()->initialize($tenant);

        if (! $tenant->staffUseCentralHost()) {
            return $this->handOffToSubdomain($request, $tenant);
        }

        if (! Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            RateLimiter::hit($this->portalThrottleKey($request));
            tenancy()->end();
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->portalThrottleKey($request));

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            tenancy()->end();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact your administrator.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('tenant_id', $tenant->id);
        $request->session()->put('tenant_slug', $tenant->slug);

        return redirect()->intended(route('portal.dashboard'));
    }

    /**
     * A workspace with its own subdomain can't be used from the portal's host, so
     * once the password checks out, carry the sign-in over there with a one-time
     * link instead of making them type it again. The subdomain finishes the login
     * itself, including 2FA.
     */
    private function handOffToSubdomain(Request $request, Tenant $tenant): SymfonyResponse
    {
        $credentials = $request->only('email', 'password');
        $user = Auth::guard('web')->validate($credentials) ? User::where('email', $credentials['email'])->first() : null;

        if (! $user) {
            RateLimiter::hit($this->portalThrottleKey($request));
            tenancy()->end();
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->portalThrottleKey($request));
        tenancy()->end();

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact your administrator.',
            ]);
        }

        $token = Crypt::encryptString(json_encode([
            'tenant' => $tenant->id,
            'user' => $user->id,
            'remember' => $request->boolean('remember'),
            'nonce' => Str::random(40),
            'expires' => now()->addSeconds(self::HANDOFF_SECONDS)->timestamp,
        ]));

        return Inertia::location($tenant->domainUrl().'/login/handoff?token='.urlencode($token));
    }

    private function ensureWorkspaceIsOpen(Tenant $tenant, string $field): void
    {
        $problem = match (true) {
            ! $tenant->isEmailVerified() => 'This workspace is not yet active. Please check your email and verify your account first.',
            $tenant->status === 'suspended' => 'This workspace has been suspended. Please contact support to reinstate your account.',
            $tenant->status === 'cancelled' => 'This workspace has been cancelled.',
            $tenant->isTrialExpired() => 'Your free trial has expired. Please contact support to upgrade your account.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages([$field => $problem]);
        }
    }

    /**
     * Same throttle pattern as LoginRequest::ensureIsNotRateLimited(), keyed
     * on workspace+email+ip since portal login also takes a workspace slug.
     */
    private function ensurePortalLoginIsNotRateLimited(Request $request): void
    {
        $key = $this->portalThrottleKey($request);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function portalThrottleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->string('workspace').'|'.$request->string('email')).'|'.$request->ip(),
        );
    }

    public function portalLogout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget(['tenant_id', 'tenant_slug']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    // ─── Forgot / Reset Password — Custom subdomain (tenant context) ──────────

    public function showForgotPassword(): Response
    {
        return Inertia::render('auth/ForgotPassword', ['isPortal' => false]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account with that email exists, we\'ve sent a password reset link.');
    }

    public function showResetPassword(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'token' => $request->route('token') ?? $request->query('token'),
            'email' => $request->query('email', ''),
            'isPortal' => false,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRules::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'force_password_reset' => false,
                ])->save();
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset successfully. Please log in.');
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }

    // ─── Forgot / Reset Password — Shared portal (central domain, no tenancy) ─

    public function showPortalForgotPassword(): Response
    {
        return Inertia::render('auth/ForgotPassword', ['isPortal' => true]);
    }

    public function sendPortalResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'workspace' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $tenant = Tenant::where('slug', $request->workspace)->first();

        // Any workspace: the portal hands subdomain workspaces over after sign-in.
        if ($tenant && $tenant->isEmailVerified()) {
            tenancy()->initialize($tenant);

            $workspace = $request->workspace;

            // Override the reset URL to include workspace for portal re-init.
            ResetPassword::createUrlUsing(function ($notifiable, string $token) use ($workspace) {
                return route('portal.password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                    'workspace' => $workspace,
                ]);
            });

            Password::sendResetLink(['email' => $request->email]);

            ResetPassword::createUrlUsing(null); // restore default

            tenancy()->end();
        }

        return back()->with('status', 'If an account with that email exists in that workspace, we\'ve sent a reset link.');
    }

    public function showPortalResetPassword(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'token' => $request->query('token', ''),
            'email' => $request->query('email', ''),
            'workspace' => $request->query('workspace', ''),
            'isPortal' => true,
        ]);
    }

    public function resetPortalPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'workspace' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRules::min(8)->mixedCase()->numbers()],
        ]);

        $tenant = Tenant::where('slug', $request->workspace)->firstOrFail();

        tenancy()->initialize($tenant);

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => $password,
                        'force_password_reset' => false,
                    ])->save();
                },
            );
        } finally {
            tenancy()->end();
        }

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('portal.login')->with('success', 'Password reset successfully. Please log in.');
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }

    // ─── Resend tenant email verification (central domain) ────────────────────

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $tenant = Tenant::where('admin_email', $request->email)
            ->whereNull('email_verified_at')
            ->first();

        if ($tenant) {
            $token = Str::random(64);
            $tenant->update(['email_verification_token' => $token]);
            $verificationUrl = route('onboarding.verify', $token);
            Mail::to($request->email)->queue(new TenantVerificationMail($tenant, $verificationUrl));
        }

        return response()->json([
            'message' => 'If an unverified account exists with that email, a new verification link has been sent.',
        ]);
    }
}
