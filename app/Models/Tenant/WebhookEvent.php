<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function markProcessed(): void
    {
        $this->update(['status' => 'processed']);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }
}
