<?php

namespace App\Models\Tenant;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'password',
        'role',
        'department',
        'branch',
        'avatar',
        'is_active',
        'force_password_reset',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'migration_source',
        'invitation_token',
        'invited_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'force_password_reset' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        // Keep the spatie role assignment in sync with the `role` enum column
        // whenever it changes, regardless of which code path saved the model.
        static::saved(function (self $user) {
            if ($user->role instanceof UserRole) {
                $role = Role::firstOrCreate(['name' => $user->role->value, 'guard_name' => 'web']);
                $user->syncRoles([$role]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ─────────────────────────────────

    public function createdLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'created_by');
    }

    public function approvedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'approved_by');
    }

    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by');
    }

    public function submittedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'submitted_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    // ─── Helpers ───────────────────────────────────────

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasRole2faEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return str_starts_with($this->avatar, 'http')
            ? $this->avatar
            : asset('storage/'.$this->avatar);
    }
}
