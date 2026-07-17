<?php

namespace App\Models\Landlord;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A public marketplace user — not tied to any tenant.
 * Authenticates via phone OTP. May later be linked to a Borrower
 * when onboarded by a tenant.
 */
class GhostUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'ghost_users';

    protected $fillable = [
        'name', 'phone', 'email',
        'national_id', 'tpin_number', 'company_reg_number',
        'national_id_hash', 'tpin_hash', 'company_reg_hash',
        'address', 'city', 'date_of_birth', 'gender',
        'is_phone_verified', 'otp_code', 'otp_expires_at', 'otp_attempts',
    ];

    protected $hidden = ['otp_code', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_phone_verified' => 'boolean',
            'otp_expires_at'    => 'datetime',
            'date_of_birth'     => 'date',
        ];
    }

    public function enquiries()
    {
        return $this->hasMany(RepoEnquiry::class, 'ghost_user_id');
    }

    public function cartItems()
    {
        return $this->hasMany(RepoCart::class, 'ghost_user_id');
    }

    public function isOtpValid(): bool
    {
        return $this->otp_expires_at && $this->otp_expires_at->isFuture()
            && $this->otp_attempts < 5;
    }
}
