<?php

namespace App\Models\Landlord;

use App\Models\FeaturedRepoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RepoItem extends Model
{
    protected $table = 'repo_items';

    protected $fillable = [
        'tenant_id', 'tenant_name', 'title', 'description',
        'price', 'original_value', 'category', 'condition', 'location',
        'is_sold', 'is_active', 'views_count', 'enquiries_count',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_value' => 'decimal:2',
            'is_sold' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(RepoItemImage::class, 'item_id')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(RepoItemImage::class, 'item_id')->where('is_primary', true);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(RepoEnquiry::class, 'item_id');
    }

    public function featuredSlots(): HasMany
    {
        return $this->hasMany(FeaturedRepoItem::class, 'repo_item_id');
    }

    public function activeFeatured(): HasOne
    {
        return $this->hasOne(FeaturedRepoItem::class, 'repo_item_id')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_sold', false);
    }

    public function scopeAvailable($query)
    {
        return $this->scopeActive($query);
    }
}
