<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The part of a payment applied to one instalment (see PaymentService). */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'loan_schedule_id', 'interest', 'fee', 'principal'];

    protected function casts(): array
    {
        return [
            'interest' => 'decimal:2',
            'fee' => 'decimal:2',
            'principal' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function instalment(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class, 'loan_schedule_id');
    }
}
