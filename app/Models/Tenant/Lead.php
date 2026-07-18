<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lead extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'lead_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'city',
        'occupation',
        'requested_amount',
        'loan_purpose',
        'source',
        'referral_name',
        'status',
        'lost_reason',
        'assigned_to',
        'converted_borrower_id',
        'follow_up_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'follow_up_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedBorrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class, 'converted_borrower_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(BorrowerInteraction::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public static function generateLeadNumber(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->value('lead_number');
        $next = $last ? ((int) substr($last, 2)) + 1 : 1;

        return 'LD'.str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public static function statuses(): array
    {
        return ['new', 'contacted', 'qualified', 'converted', 'lost'];
    }

    public static function sources(): array
    {
        return ['walk_in', 'referral', 'social_media', 'website', 'agent', 'staff', 'campaign', 'other'];
    }
}
