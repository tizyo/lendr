<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class RepoEnquiry extends Model
{
    protected $table = 'repo_enquiries';

    protected $fillable = [
        'item_id', 'ghost_user_id', 'message', 'status', 'reply', 'replied_at',
    ];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    public function item()
    {
        return $this->belongsTo(RepoItem::class, 'item_id');
    }

    public function ghostUser()
    {
        return $this->belongsTo(GhostUser::class, 'ghost_user_id');
    }
}
