<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanOfferRule extends Model
{
    protected $fillable = [
        'name',
        'min_credit_score',
        'max_credit_score',
        'loan_plan_id',
        'min_offered_amount',
        'max_offered_amount',
        'validity_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_offered_amount' => 'float',
            'max_offered_amount' => 'float',
            'is_active'          => 'boolean',
        ];
    }

    public function loanPlan(): BelongsTo
    {
        return $this->belongsTo(LoanPlan::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LoanOffer::class);
    }

    /** Find rules that apply to a given credit score. */
    public static function forScore(int $score): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->where('min_credit_score', '<=', $score)
            ->where('max_credit_score', '>=', $score)
            ->get();
    }
}
