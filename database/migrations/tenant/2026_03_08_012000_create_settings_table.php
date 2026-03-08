<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json, encrypted
            $table->string('group')->nullable(); // general, sms, mobile_money, notifications
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 15, 6);
            $table->date('effective_date');
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency', 'effective_date']);
        });

        Schema::create('mobile_money_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // airtel, mtn, zamtel
            $table->string('transaction_id')->unique(); // provider's transaction ref
            $table->string('internal_ref')->unique(); // LENDR internal ref
            $table->morphs('transactable'); // loan (disbursement) or payment
            $table->string('phone');
            $table->decimal('amount', 15, 2);
            $table->string('currency')->default('ZMW');
            $table->enum('direction', ['outbound', 'inbound']); // disburse or repayment
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'reversed'])->default('pending');
            $table->text('provider_response')->nullable(); // raw JSON from provider
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('phone');
        });

        // Webhook event log for audit and idempotency
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id')->unique(); // provider event ID for idempotency
            $table->string('event_type');
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'failed', 'skipped'])->default('received');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('mobile_money_transactions');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('settings');
    }
};
