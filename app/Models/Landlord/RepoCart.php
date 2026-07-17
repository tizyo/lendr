<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class RepoCart extends Model
{
    protected $table = 'repo_carts';

    protected $fillable = ['ghost_user_id', 'item_id', 'notes'];

    public function item()
    {
        return $this->belongsTo(RepoItem::class, 'item_id');
    }

    public function ghostUser()
    {
        return $this->belongsTo(GhostUser::class, 'ghost_user_id');
    }
}
