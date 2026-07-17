<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionCase extends Model
{
    protected $fillable = [
        'loan_id', 'borrower_id', 'assigned_to', 'escalation_rule_id',
        'status', 'action_taken', 'dpd_at_creation', 'notes', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'dpd_at_creation' => 'integer'];
    }

    public function loan(): BelongsTo      { return $this->belongsTo(Loan::class); }
    public function borrower(): BelongsTo  { return $this->belongsTo(Borrower::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function escalationRule(): BelongsTo { return $this->belongsTo(EscalationRule::class); }
    public function promises(): HasMany    { return $this->hasMany(PromiseToPay::class); }
}
