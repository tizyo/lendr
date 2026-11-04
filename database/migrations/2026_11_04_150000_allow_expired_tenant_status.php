<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "expired" is what lendr:process-trial-expiry (and the landlord's status menu)
 * set on a lapsed trial, but the column only allowed active/suspended/trial/
 * cancelled: on MySQL every such update failed, so a lapsed trial was never
 * marked and the daily job errored on the first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'trial', 'cancelled', 'expired'])->default('trial')->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('tenants')->where('status', 'expired')->update(['status' => 'suspended']);

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'trial', 'cancelled'])->default('trial')->change();
        });
    }
};
