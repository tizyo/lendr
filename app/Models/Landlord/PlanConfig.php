<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlanConfig extends Model
{
    protected $fillable = [
        'plan',
        'label',
        'description',
        'price_zmw',
        'is_custom_price',
        'features',
    ];

    protected $casts = [
        'price_zmw'       => 'decimal:2',
        'is_custom_price' => 'boolean',
        'features'        => 'array',
    ];

    /** @return static|null */
    public static function forPlan(string $plan): ?self
    {
        if (! Schema::hasTable('plan_configs')) {
            return null;
        }

        return static::where('plan', $plan)->first();
    }

    /** Returns all plans keyed by plan slug. Returns [] if table not yet migrated. */
    public static function allKeyed(): array
    {
        if (! Schema::hasTable('plan_configs')) {
            return [];
        }

        return static::all()->keyBy('plan')->all();
    }

    /** Get a specific feature value for this plan. */
    public function feature(string $key, mixed $default = null): mixed
    {
        return $this->features[$key] ?? $default;
    }

    /** True if the feature is a boolean and enabled, or numeric and unlimited (-1). */
    public function hasFeature(string $key): bool
    {
        $value = $this->features[$key] ?? false;
        return $value === true || $value === -1;
    }

    /** True if usage is within the plan limit (-1 = unlimited). */
    public function withinLimit(string $key, int $current): bool
    {
        $limit = (int) ($this->features[$key] ?? 0);
        if ($limit === -1) return true;
        return $current < $limit;
    }
}
