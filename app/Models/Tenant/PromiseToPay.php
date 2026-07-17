<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromiseToPay extends Model
{
    protected $fillable = [
        'collection_case_id', 'loan_id', 'promise_date', 'promise_amount', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['promise_date' => 'date', 'promise_amount' => 'decimal:2'];
    }

    public function collectionCase(): BelongsTo { return $this->belongsTo(CollectionCase::class); }
    public function loan(): BelongsTo           { return $this->belongsTo(Loan::class); }
    public function creator(): BelongsTo        { return $this->belongsTo(User::class, 'created_by'); }
}
