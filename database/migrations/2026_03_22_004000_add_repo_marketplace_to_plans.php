<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add repo_marketplace feature flag to plan configs.
 * Growth and Enterprise plans can post repossessed items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('plan_configs')) {
            return;
        }

        $plans = DB::table('plan_configs')->get();

        foreach ($plans as $plan) {
            $features = json_decode($plan->features, true) ?? [];
            $features['repo_marketplace'] = in_array($plan->plan, ['growth', 'enterprise']);

            DB::table('plan_configs')
                ->where('id', $plan->id)
                ->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('plan_configs')) {
            return;
        }

        $plans = DB::table('plan_configs')->get();

        foreach ($plans as $plan) {
            $features = json_decode($plan->features, true) ?? [];
            unset($features['repo_marketplace']);

            DB::table('plan_configs')
                ->where('id', $plan->id)
                ->update(['features' => json_encode($features)]);
        }
    }
};
