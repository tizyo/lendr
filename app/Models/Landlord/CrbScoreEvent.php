<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class CrbScoreEvent extends Model
{
    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

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
