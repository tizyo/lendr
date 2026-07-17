<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->index();
            $table->string('plan', 32);                        // starter|growth|enterprise
            $table->string('status', 16)->default('pending');  // pending|active|cancelled|expired
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_tx_ref', 128)->nullable()->unique();
            $table->decimal('amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('ZMW');
            $table->string('billing_cycle', 16)->default('monthly'); // monthly|annual
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
