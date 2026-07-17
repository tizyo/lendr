<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotDealLead extends Model
{
    protected $fillable = [
        'hot_deal_id',
        'full_name',
        'phone',
        'email',
        'message',
        'ip_address',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(HotDeal::class, 'hot_deal_id');
    }
}
