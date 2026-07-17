<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class CrbScoreEvent extends Model
{
    protected $table = 'crb_score_events';

    public $timestamps = false;

    protected $fillable = [
        'identity_hash', 'event_type', 'points_change', 'score_before', 'score_after',
        'dpd', 'tenant_id', 'loan_reference_hash', 'description', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
