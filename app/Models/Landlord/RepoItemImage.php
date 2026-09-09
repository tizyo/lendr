<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;

class RepoItemImage extends Model
{
    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    protected $table = 'repo_item_images';

    protected $fillable = ['item_id', 'image_url', 'caption', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
