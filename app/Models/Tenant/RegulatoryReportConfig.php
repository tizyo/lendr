<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class RegulatoryReportConfig extends Model
{
    protected $fillable = [
        'report_type',
        'name',
        'frequency',
        'recipient_emails',
        'is_active',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function recipientList(): array
    {
        return array_filter(array_map('trim', explode(',', $this->recipient_emails)));
    }
}
