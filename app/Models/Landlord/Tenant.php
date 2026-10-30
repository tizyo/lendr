<?php

namespace App\Models\Landlord;

use App\Services\PlanFeatureService;
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

    // ─── URLs (safe in queued jobs, where url() would point at APP_URL) ─────

    /** Root of the tenant's own subdomain, or null when it has none. */
    public function domainUrl(): ?string
    {
        $domain = $this->domains()->value('domain');

        if (! $domain) {
            return null;
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$domain}";
    }

    /** Where this tenant's staff sign in. */
    public function loginUrl(): string
    {
        $base = $this->usesSharedPortal() ? null : $this->domainUrl();

        return $base ? $base.'/login' : route('portal.login');
    }

    /** True when staff work on the central host (identified by session), not a subdomain. */
    public function staffUseCentralHost(): bool
    {
        return $this->usesSharedPortal() || ! $this->domains()->exists();
    }

    /** Whether a staff session for this tenant belongs on the given host. */
    public function servesStaffHost(string $host): bool
    {
        return $this->staffUseCentralHost()
            ? in_array($host, config('tenancy.central_domains', []))
            : $this->domains()->where('domain', $host)->exists();
    }

    /**
     * A page in the borrower self-service portal (e.g. '/loans'), or null when
     * borrowers have no portal: it lives only on the tenant's own subdomain and
     * only on plans with the "pwa" feature.
     */
    public function borrowerPortalUrl(string $path = ''): ?string
    {
        $base = $this->domainUrl();

        if (! $base || ! (new PlanFeatureService($this->plan))->has('pwa')) {
            return null;
        }

        return $base.'/app'.$path;
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
