<?php

namespace App\Models\Tenant;

use App\Enums\PaymentMethod;
use Database\Factories\Tenant\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected $fillable = [
        'receipt_number',
        'loan_id',
        'recorded_by',
        'amount',
        'principal_allocated',
        'interest_allocated',
        'penalty_allocated',
        'fee_allocated',
        'payment_method',
        'payment_date',
        'reference',
        'momo_transaction_id',
        'momo_provider',
        'source',
        'is_overdue_payment',
        'notes',
        'legacy_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_method'       => PaymentMethod::class,
            'payment_date'         => 'date',
            'amount'               => 'decimal:2',
            'principal_allocated'  => 'decimal:2',
            'interest_allocated'   => 'decimal:2',
            'penalty_allocated'    => 'decimal:2',
            'fee_allocated'        => 'decimal:2',
            'is_overdue_payment'   => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'payment_method', 'payment_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
