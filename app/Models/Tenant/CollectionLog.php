<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionLog extends Model
{
    protected $fillable = [
        'loan_id',
        'officer_id',
        'contact_method',
        'outcome',
        'notes',
        'follow_up_date',
        'amount_promised',
        'amount_collected',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'amount_promised' => 'decimal:2',
        'amount_collected' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    // ─── Label helpers ────────────────────────────────────────────────────────

    public function contactMethodLabel(): string
    {
        return match ($this->contact_method) {
            'call' => 'Phone Call',
            'sms' => 'SMS',
            'visit' => 'Field Visit',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            default => ucfirst($this->contact_method),
        };
    }

    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            'reached' => 'Reached',
            'no_answer' => 'No Answer',
            'promised_payment' => 'Promised Payment',
            'partial_payment' => 'Partial Payment',
            'paid_up' => 'Paid Up',
            'refused' => 'Refused',
            'invalid_number' => 'Invalid Number',
            'rescheduled' => 'Rescheduled',
            default => ucfirst(str_replace('_', ' ', $this->outcome)),
        };
    }

    public function outcomeColor(): string
    {
        return match ($this->outcome) {
            'promised_payment', 'rescheduled' => 'amber',
            'partial_payment', 'reached' => 'blue',
            'paid_up' => 'emerald',
            'refused', 'invalid_number' => 'red',
            'no_answer' => 'neutral',
            default => 'neutral',
        };
    }
}
