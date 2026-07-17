<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Jobs\SendOtpSmsJob;
use App\Models\Tenant\Borrower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class BorrowerAuthController extends BaseApiController
{
    // Zambian phone number regex: 09x / 07x / 06x or +260 equivalent
    private const PHONE_REGEX = '/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/';

    // OTP validity in minutes
    private const OTP_TTL_MINUTES = 5;

    // Max OTP requests per hour per phone
    private const OTP_RATE_LIMIT = 3;

    // ─── Request OTP ──────────────────────────────────────────────────────────

    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
        ], [
            'phone.regex' => 'Please enter a valid Zambian phone number (e.g. 0971234567).',
        ]);

        $phone       = $this->normalisePhone($request->phone);
        $throttleKey = 'otp-request:'.$phone;

        if (RateLimiter::tooManyAttempts($throttleKey, self::OTP_RATE_LIMIT)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->error("Too many OTP requests. Try again in {$seconds} seconds.", 429);
        }

        // Find or create a borrower record for this phone
        $borrower = Borrower::firstOrCreate(
            ['phone' => $phone],
            [
                'borrower_number' => $this->generateBorrowerNumber(),
                'first_name'      => 'New',
                'last_name'       => 'Borrower',
                'is_active'       => true,
            ]
        );

        if ($borrower->is_blacklisted) {
            return $this->error('This account has been suspended. Contact support.', 403);
        }

        $otp       = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash   = Hash::make($otp);
        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);

        $borrower->forceFill([
            'otp'            => $otpHash,
            'otp_expires_at' => $expiresAt,
        ])->save();

        RateLimiter::hit($throttleKey, 3600); // 1-hour window

        dispatch(new SendOtpSmsJob($phone, $otp, self::OTP_TTL_MINUTES));

        return $this->success([
            'expires_in' => self::OTP_TTL_MINUTES * 60,
        ], 'OTP sent to your phone number.');
    }

    // ─── Verify OTP ───────────────────────────────────────────────────────────

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'digits:6'],
        ]);

        $phone    = $this->normalisePhone($request->phone);
        $borrower = Borrower::where('phone', $phone)->first();

        if (! $borrower) {
            return $this->error('Phone number not found.', 404);
        }

        if (! $borrower->isOtpValid()) {
            return $this->error('OTP has expired. Please request a new one.', 422);
        }

        if (! Hash::check($request->otp, $borrower->otp)) {
            return $this->error('Invalid OTP.', 422);
        }

        // Invalidate the OTP after use
        $borrower->forceFill([
            'otp'            => null,
            'otp_expires_at' => null,
            'last_login_at'  => now(),
        ])->save();

        $token = $borrower->createToken('pwa-session')->plainTextToken;

        return $this->success([
            'token'    => $token,
            'borrower' => $this->formatBorrower($borrower),
        ], 'OTP verified. Welcome to LENDR.');
    }

    // ─── Set PIN ──────────────────────────────────────────────────────────────

    public function setPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin'              => ['required', 'digits_between:4,6'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        /** @var Borrower $borrower */
        $borrower = $request->user();
        $borrower->forceFill(['pin' => Hash::make($request->pin)])->save();

        return $this->success(null, 'PIN set successfully.');
    }

    // ─── Login with PIN ───────────────────────────────────────────────────────

    public function loginPin(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'pin'   => ['required', 'digits_between:4,6'],
        ]);

        $phone    = $this->normalisePhone($request->phone);
        $borrower = Borrower::where('phone', $phone)->first();

        if (! $borrower || ! $borrower->pin || ! Hash::check($request->pin, $borrower->pin)) {
            return $this->error('Invalid phone number or PIN.', 401);
        }

        if (! $borrower->is_active || $borrower->is_blacklisted) {
            return $this->error('Account suspended. Contact support.', 403);
        }

        $borrower->updateQuietly(['last_login_at' => now()]);
        $token = $borrower->createToken('pwa-pin-session')->plainTextToken;

        return $this->success([
            'token'    => $token,
            'borrower' => $this->formatBorrower($borrower),
        ], 'Login successful.');
    }

    // ─── Refresh Token ────────────────────────────────────────────────────────

    public function refreshToken(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $request->user()->currentAccessToken()->delete();
        $token = $borrower->createToken('pwa-session')->plainTextToken;

        return $this->success(['token' => $token], 'Token refreshed.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function normalisePhone(string $phone): string
    {
        // Normalise +260XXXXXXXXX → 0XXXXXXXXX
        if (Str::startsWith($phone, '+260')) {
            return '0'.substr($phone, 4);
        }

        return $phone;
    }

    private function generateBorrowerNumber(): string
    {
        $prefix = 'BRW-'.now()->format('Ym').'-';
        $last   = Borrower::withTrashed()->where('borrower_number', 'like', $prefix.'%')->max('borrower_number');
        $seq    = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function formatBorrower(Borrower $borrower): array
    {
        return [
            'id'              => $borrower->id,
            'borrower_number' => $borrower->borrower_number,
            'full_name'       => $borrower->full_name,
            'phone'           => $borrower->phone,
            'email'           => $borrower->email,
            'kyc_verified'    => $borrower->kyc_verified,
            'has_pin'         => ! is_null($borrower->pin),
            'portal_access'   => $borrower->portal_access ?? false,
        ];
    }
}
