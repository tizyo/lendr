<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class RegulatoryReport extends Model
{
    protected $fillable = [
        'report_type',
        'period',
        'data',
        'generated_by',
        'emailed',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'emailed' => 'boolean',
            'emailed_at' => 'datetime',
        ];
    }
}
