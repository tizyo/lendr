<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandingOrder extends Model
{
    protected $fillable = [
        'loan_id',
        'loan_schedule_id',
        'borrower_id',
        'amount',
        'phone',
        'gateway',
        'due_date',
        'status',
        'retry_count',
        'max_retries',
        'next_attempt_at',
        'processed_at',
        'payment_id',
        'provider_reference',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'next_attempt_at' => 'datetime',
            'processed_at' => 'datetime',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class, 'loan_schedule_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Mark as failed; schedule a retry if attempts remain. */
    public function recordFailure(string $reason): void
    {
        $newCount = $this->retry_count + 1;

        if ($newCount >= $this->max_retries) {
            $this->update([
                'status' => 'failed',
                'retry_count' => $newCount,
                'failure_reason' => $reason,
            ]);
        } else {
            $this->update([
                'status' => 'pending',
                'retry_count' => $newCount,
                'failure_reason' => $reason,
                'next_attempt_at' => now()->addDays($newCount),
            ]);
        }
    }
}
