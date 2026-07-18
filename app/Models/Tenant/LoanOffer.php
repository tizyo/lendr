<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanOffer extends Model
{
    protected $fillable = [
        'loan_offer_rule_id',
        'borrower_id',
        'loan_plan_id',
        'offered_amount',
        'interest_rate',
        'tenure',
        'credit_score_at_offer',
        'status',
        'expires_at',
        'accepted_at',
        'declined_at',
        'decline_reason',
        'created_loan_id',
    ];

    protected function casts(): array
    {
        return [
            'offered_amount' => 'float',
            'interest_rate' => 'float',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function loanPlan(): BelongsTo
    {
        return $this->belongsTo(LoanPlan::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LoanOfferRule::class, 'loan_offer_rule_id');
    }

    public function createdLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'created_loan_id');
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }
}
