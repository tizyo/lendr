<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_configs')) {
            return;
        }

        $enterprise = DB::table('plan_configs')->where('plan', 'enterprise')->first();

        if (! $enterprise) {
            return;
        }

        $features = json_decode($enterprise->features, true) ?? [];

        // Add custom comms flags — only Enterprise gets them
        $features['custom_email'] = true;
        $features['custom_sms']   = true;

        DB::table('plan_configs')
            ->where('plan', 'enterprise')
            ->update(['features' => json_encode($features)]);

        // Explicitly ensure starter and growth do NOT have these
        foreach (['starter', 'growth'] as $plan) {
            $row = DB::table('plan_configs')->where('plan', $plan)->first();
            if (! $row) continue;
            $f = json_decode($row->features, true) ?? [];
            $f['custom_email'] = false;
            $f['custom_sms']   = false;
            DB::table('plan_configs')->where('plan', $plan)->update(['features' => json_encode($f)]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_configs')) return;

        foreach (['starter', 'growth', 'enterprise'] as $plan) {
            $row = DB::table('plan_configs')->where('plan', $plan)->first();
            if (! $row) continue;
            $f = json_decode($row->features, true) ?? [];
            unset($f['custom_email'], $f['custom_sms']);
            DB::table('plan_configs')->where('plan', $plan)->update(['features' => json_encode($f)]);
        }
    }
};
