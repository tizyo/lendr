<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SavingsAccount extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'borrower_id',
        'opened_by',
        'account_number',
        'type',
        'balance',
        'interest_rate',
        'maturity_date',
        'target_amount',
        'status',
        'opened_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'target_amount' => 'decimal:2',
            'maturity_date' => 'date',
            'opened_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public static function generateAccountNumber(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->value('account_number');
        $next = $last ? ((int) substr($last, 3)) + 1 : 1;

        return 'SAV'.str_pad($next, 7, '0', STR_PAD_LEFT);
    }

    public function accrueInterest(): ?SavingsTransaction
    {
        if ((float) $this->interest_rate === 0.0 || (float) $this->balance <= 0) {
            return null;
        }

        // Monthly interest = annual_rate / 12 * balance
        $interest = round((float) $this->balance * ((float) $this->interest_rate / 100) / 12, 2);

        if ($interest <= 0) {
            return null;
        }

        $newBalance = (float) $this->balance + $interest;

        $this->update(['balance' => $newBalance]);

        return $this->transactions()->create([
            'recorded_by' => null,
            'type' => 'interest',
            'amount' => $interest,
            'balance_after' => $newBalance,
            'transaction_date' => now()->toDateString(),
            'notes' => 'Monthly interest accrual',
        ]);
    }
}
