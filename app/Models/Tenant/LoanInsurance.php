<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LoanInsurance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loan_id',
        'insurance_product_id',
        'recorded_by',
        'policy_number',
        'sum_insured',
        'premium_amount',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sum_insured'    => 'decimal:2',
            'premium_amount' => 'decimal:2',
            'start_date'     => 'date',
            'end_date'       => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InsuranceProduct::class, 'insurance_product_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public static function generatePolicyNumber(): string
    {
        return 'POL-'.now()->format('Ym').'-'.strtoupper(Str::random(6));
    }
}
