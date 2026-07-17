<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class RepoItemImage extends Model
{
    protected $table = 'repo_item_images';

    protected $fillable = ['item_id', 'image_url', 'caption', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
