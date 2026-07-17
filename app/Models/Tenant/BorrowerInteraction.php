<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerInteraction extends Model
{
    protected $fillable = [
        'lead_id',
        'borrower_id',
        'recorded_by',
        'channel',
        'direction',
        'outcome',
        'notes',
        'follow_up_date',
        'amount_discussed',
        'interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date'  => 'date',
            'amount_discussed'=> 'decimal:2',
            'interaction_at'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ─── Enum lists ──────────────────────────────────────────────────────────

    public static function channels(): array
    {
        return ['call', 'visit', 'email', 'sms', 'whatsapp', 'other'];
    }

    public static function outcomes(): array
    {
        return ['no_answer', 'left_message', 'spoke_to_borrower', 'meeting_scheduled', 'promise_to_pay', 'declined', 'completed'];
    }
}
