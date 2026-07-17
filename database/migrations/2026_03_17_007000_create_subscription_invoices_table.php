<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->char('tenant_id', 36)->index();
            $table->string('gateway', 32);
            $table->string('gateway_tx_ref', 128)->unique(); // LENDR-SUB-{uuid}
            $table->string('gateway_tx_id', 128)->nullable();
            $table->string('plan', 32);
            $table->decimal('amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('ZMW');
            $table->string('billing_cycle', 16)->default('monthly');
            $table->string('status', 16)->default('pending'); // pending|paid|failed
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
