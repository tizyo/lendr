<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_phone');
            $table->string('recipient_name')->nullable();
            $table->text('message');
            $table->string('template')->nullable(); // template key
            $table->string('gateway')->nullable(); // twilio, sms_to, clickatell
            $table->string('gateway_message_id')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->text('error_message')->nullable();
            $table->morphs('notifiable'); // loan, payment, borrower, etc.
            $table->timestamps();

            $table->index('status');
            $table->index('recipient_phone');
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('key')->unique(); // e.g. loan_approved, payment_received
            $table->text('body'); // template with {variables}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // notification type key
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('icon')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('sms_logs');
    }
};
