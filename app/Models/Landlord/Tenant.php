<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'plan',
        'status',
        'currency',
        'timezone',
        'logo',
        'admin_email',
        'email_verification_token',
        'email_verified_at',
        'trial_ends_at',
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_note',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'email_verified_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /** Plans that share the app portal (no custom subdomain). */
    public function usesSharedPortal(): bool
    {
        return in_array($this->plan, ['starter', 'trial']);
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'plan',
            'status',
            'currency',
            'timezone',
            'logo',
            'admin_email',
            'email_verification_token',
            'email_verified_at',
            'trial_ends_at',
            'is_verified',
            'verified_at',
            'verified_by',
            'verification_note',
        ];
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial';
    }

    /**
     * Returns the verification badge tier for public display.
     * Enterprise plan → always 'gold'. Others → 'gold' if manually verified, null otherwise.
     */
    public function verificationBadge(): ?string
    {
        if ($this->plan === 'enterprise') {
            return 'gold';
        }
        if ($this->is_verified) {
            return 'gold';
        }

        return null;
    }

    public function isTrialExpired(): bool
    {
        return $this->isOnTrial()
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    /** Days remaining in trial (0 if expired or no end date set). */
    public function trialDaysRemaining(): int
    {
        if (! $this->isOnTrial() || ! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }
}
