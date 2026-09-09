<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReply extends Model
{
    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    protected $fillable = [
        'ticket_id',
        'author_type',
        'author_name',
        'message',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
