<?php

namespace App\Models\Tenant;

use Database\Factories\BorrowerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Borrower extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'borrower_number',
        'first_name',
        'last_name',
        'other_names',
        'email',
        'phone',
        'phone_alt',
        'gender',
        'date_of_birth',
        'national_id',
        'tpin_number',
        'company_reg_number',
        'occupation',
        'employer',
        'address',
        'city',
        'province',
        'country',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_relationship',
        'avatar',
        'is_active',
        'is_blacklisted',
        'blacklist_reason',
        'kyc_verified',
        'credit_score',
        'credit_score_updated_at',
        'verification_tier',
        'migration_source',
        'legacy_id',
        'created_by',
        'ghost_user_id',
    ];

    protected $hidden = [
        'pin',
        'otp',
        'national_id_hash',
    ];

    protected function casts(): array
    {
        return [
            // NRC is PII (Data Protection Act 2021): encrypted at rest. Exact lookups and the
            // uniqueness rule use national_id_hash instead (see nationalIdHash()).
            'national_id' => 'encrypted',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'is_blacklisted' => 'boolean',
            'kyc_verified' => 'boolean',
            'otp_expires_at' => 'datetime',
            'credit_score_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the blind index in step with the encrypted NRC.
        static::saving(function (self $borrower) {
            if ($borrower->isDirty('national_id')) {
                $borrower->national_id_hash = self::nationalIdHash($borrower->national_id);
            }
        });
    }

    /**
     * Keyed hash of an NRC for exact-match search and uniqueness — the column
     * itself is encrypted with a random IV, so it can't be compared directly.
     * Formatting is ignored: "123456/78/1" and "123456781" match.
     */
    public static function nationalIdHash(?string $nationalId): ?string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $nationalId));

        return $normalized === '' ? null : hash_hmac('sha256', 'nrc|'.$normalized, (string) config('app.key'));
    }

    /** Ciphertext for raw inserts that bypass the model (e.g. legacy data migration). */
    public static function encryptNationalId(?string $nationalId): ?string
    {
        return filled($nationalId) ? Crypt::encryptString($nationalId) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'phone', 'email', 'is_active', 'is_blacklisted', 'kyc_verified'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ─────────────────────────────────

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->whereIn('status', ['disbursed', 'active']);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function savingsAccounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }

    public function loanGroupMemberships(): HasMany
    {
        return $this->hasMany(LoanGroupMember::class);
    }

    // ─── Computed ──────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->other_names} {$this->last_name}");
    }

    public function getTotalBorrowedAttribute(): string
    {
        return $this->loans()->whereIn('status', ['disbursed', 'active', 'completed'])->sum('principal_amount');
    }

    public function getOutstandingBalanceAttribute(): string
    {
        return $this->activeLoans()->sum('outstanding_balance');
    }

    /**
     * Compute the verification tier from a credit score.
     * null  → no badge  (brand new, no history)
     * grey  → 300–549   (entry level)
     * yellow→ 550–699   (established)
     * blue  → 700–850   (excellent)
     */
    public static function tierFromScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }
        if ($score >= 700) {
            return 'blue';
        }
        if ($score >= 550) {
            return 'yellow';
        }

        return 'grey';
    }

    public static function generateBorrowerNumber(): string
    {
        $prefix = 'BRW-'.now()->format('Ym').'-';
        $last = self::withTrashed()
            ->where('borrower_number', 'like', "{$prefix}%")
            ->max('borrower_number');

        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    protected static function newFactory(): BorrowerFactory
    {
        return BorrowerFactory::new();
    }

    public function isOtpValid(): bool
    {
        return $this->otp_expires_at && $this->otp_expires_at->isFuture();
    }

    /**
     * Returns the primary CRB identifier value and type for this borrower.
     * Priority: NRC → TPIN → Company Reg
     *
     * @return array{value: string, type: string}|null
     */
    public function crbIdentifier(): ?array
    {
        if ($this->national_id) {
            return ['value' => $this->national_id, 'type' => 'nrc'];
        }
        if ($this->tpin_number) {
            return ['value' => $this->tpin_number, 'type' => 'tpin'];
        }
        if ($this->company_reg_number) {
            return ['value' => $this->company_reg_number, 'type' => 'company_reg'];
        }

        return null;
    }

    /** Returns all available CRB identifiers for composite checking. */
    public function allCrbIdentifiers(): array
    {
        $ids = [];
        if ($this->national_id) {
            $ids[] = ['value' => $this->national_id, 'type' => 'nrc'];
        }
        if ($this->tpin_number) {
            $ids[] = ['value' => $this->tpin_number, 'type' => 'tpin'];
        }
        if ($this->company_reg_number) {
            $ids[] = ['value' => $this->company_reg_number, 'type' => 'company_reg'];
        }

        return $ids;
    }
}
