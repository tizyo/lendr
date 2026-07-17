<?php

namespace App\Services;

use App\Models\Landlord\GhostUser;
use Illuminate\Support\Str;

class GhostUserService
{
    private const OTP_TTL_MINUTES = 10;
    private const OTP_LENGTH = 6;

    /**
     * Register a new ghost user or return existing if phone already taken.
     */
    public function register(string $phone, string $name, ?string $email = null): GhostUser
    {
        return GhostUser::firstOrCreate(
            ['phone' => $phone],
            ['name' => $name, 'email' => $email, 'is_phone_verified' => false],
        );
    }

    /**
     * Generate and store a new OTP for a ghost user.
     * Returns the plain-text OTP (to be sent via SMS).
     */
    public function generateOtp(GhostUser $user): string
    {
        $otp = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        $user->update([
            'otp_code'       => $otp,
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_attempts'   => 0,
        ]);

        return $otp;
    }

    /**
     * Verify an OTP. Returns true on success, false on failure.
     * Increments attempt counter to prevent brute-force.
     */
    public function verifyOtp(GhostUser $user, string $otp): bool
    {
        if (! $user->isOtpValid()) {
            return false;
        }

        $user->increment('otp_attempts');

        if ($user->otp_code !== $otp) {
            return false;
        }

        // Clear OTP and mark verified
        $user->update([
            'is_phone_verified' => true,
            'otp_code'          => null,
            'otp_expires_at'    => null,
            'otp_attempts'      => 0,
        ]);

        return true;
    }

    /**
     * Find a ghost user by phone.
     */
    public function findByPhone(string $phone): ?GhostUser
    {
        return GhostUser::where('phone', $phone)->first();
    }

    /**
     * Look up a ghost user by one or more identity hashes.
     */
    public function findByIdentifiers(
        ?string $nationalId = null,
        ?string $tpin = null,
        ?string $companyReg = null,
    ): ?GhostUser {
        $query = GhostUser::query();
        $found = false;

        if ($nationalId) {
            $hash = $this->hash($nationalId, 'nrc');
            $query->orWhere('national_id_hash', $hash);
            $found = true;
        }

        if ($tpin) {
            $hash = $this->hash($tpin, 'tpin');
            $query->orWhere('tpin_hash', $hash);
            $found = true;
        }

        if ($companyReg) {
            $hash = $this->hash($companyReg, 'company_reg');
            $query->orWhere('company_reg_hash', $hash);
            $found = true;
        }

        return $found ? $query->first() : null;
    }

    /**
     * Update identity hashes on a ghost user when they provide their NRC/TPIN.
     */
    public function syncIdentityHashes(GhostUser $user): void
    {
        $updates = [];

        if ($user->national_id && ! $user->national_id_hash) {
            $updates['national_id_hash'] = $this->hash($user->national_id, 'nrc');
        }

        if ($user->tpin_number && ! $user->tpin_hash) {
            $updates['tpin_hash'] = $this->hash($user->tpin_number, 'tpin');
        }

        if ($user->company_reg_number && ! $user->company_reg_hash) {
            $updates['company_reg_hash'] = $this->hash($user->company_reg_number, 'company_reg');
        }

        if ($updates) {
            $user->update($updates);
        }
    }

    public function hash(string $value, string $type): string
    {
        return hash('sha256', strtolower($type) . ':' . strtolower(trim($value)));
    }
}
