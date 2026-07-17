<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LoanGroup extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'group_number',
        'officer_id',
        'description',
        'meeting_schedule',
        'meeting_location',
        'status',
        'max_members',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LoanGroupMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(LoanGroupMember::class)->where('is_active', true);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public static function generateGroupNumber(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->value('group_number');
        $next = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'GRP'.str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
