<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class CrbInquiry extends Model
{
    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    protected $table = 'crb_inquiries';

    public $timestamps = false;

    protected $fillable = [
        'identity_hash', 'tenant_id', 'purpose',
        'result_score', 'result_active_loans', 'result_risk_level',
        'result_has_active_loans', 'override_requested', 'override_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'result_has_active_loans' => 'boolean',
            'override_requested' => 'boolean',
        ];
    }
}
