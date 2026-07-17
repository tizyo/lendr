<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\GhostUser;
use App\Services\GhostUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ghost-user authentication (public marketplace).
 *
 * Flow: register (phone + name) → request-otp → verify-otp → token issued.
 * Tokens are Sanctum personal access tokens with tokenable_type = GhostUser.
 */
class GhostAuthController extends BaseApiController
{
    public function __construct(private GhostUserService $svc) {}

    /**
     * POST /api/v1/public/auth/register
     * Register a new ghost account (idempotent — returns existing if phone taken).
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
        ]);

        $user = $this->svc->register($data['phone'], $data['name'], $data['email'] ?? null);

        return $this->success([
            'ghost_user_id'     => $user->id,
            'is_phone_verified' => $user->is_phone_verified,
        ], 'Account ready. Request an OTP to sign in.', 201);
    }

    /**
     * POST /api/v1/public/auth/request-otp
     * Generate and (in production) send OTP via SMS.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string']]);

        $user = $this->svc->findByPhone($request->phone);

        if (! $user) {
            return $this->error('No account found for this number. Please register first.', 404);
        }

        $otp = $this->svc->generateOtp($user);

        // In production: dispatch SMS job here
        // dispatch(new SendSmsJob($user->phone, "Your LENDR code: {$otp}"));

        $response = ['message' => 'OTP sent to your phone.'];

        // Expose OTP in non-production for easy testing
        if (! app()->isProduction()) {
            $response['otp'] = $otp;
        }

        return $this->success($response, 'OTP sent.');
    }

    /**
     * POST /api/v1/public/auth/verify-otp
     * Verify OTP and return a Sanctum token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $user = $this->svc->findByPhone($data['phone']);

        if (! $user) {
            return $this->error('Account not found.', 404);
        }

        if (! $this->svc->verifyOtp($user, $data['otp'])) {
            return $this->error('Invalid or expired OTP.', 422);
        }

        $token = $user->createToken('marketplace')->plainTextToken;

        return $this->success([
            'token'      => $token,
            'ghost_user' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'phone'             => $user->phone,
                'email'             => $user->email,
                'is_phone_verified' => $user->is_phone_verified,
            ],
        ], 'Signed in successfully.');
    }

    /**
     * GET /api/v1/public/auth/profile  [ghost auth]
     * Return current ghost user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var GhostUser $user */
        $user = $request->user();

        return $this->success([
            'id'                => $user->id,
            'name'              => $user->name,
            'phone'             => $user->phone,
            'email'             => $user->email,
            'address'           => $user->address,
            'city'              => $user->city,
            'date_of_birth'     => $user->date_of_birth?->toDateString(),
            'gender'            => $user->gender,
            'is_phone_verified' => $user->is_phone_verified,
        ]);
    }

    /**
     * PUT /api/v1/public/auth/profile  [ghost auth]
     * Update profile and sync identity hashes.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var GhostUser $user */
        $user = $request->user();

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:100'],
            'email'              => ['sometimes', 'nullable', 'email', 'max:100'],
            'address'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'               => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_of_birth'      => ['sometimes', 'nullable', 'date'],
            'gender'             => ['sometimes', 'nullable', 'in:male,female,other'],
            'national_id'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'tpin_number'        => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_reg_number' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $user->update($data);
        $this->svc->syncIdentityHashes($user->fresh());

        return $this->success(['message' => 'Profile updated.']);
    }
}
