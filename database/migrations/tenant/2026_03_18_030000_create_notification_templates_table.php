<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            // Event that triggers this template
            $table->string('event', 80); // e.g. loan_approved, payment_received, overdue_day_1
            // Channel: sms | email
            $table->string('channel', 10);
            $table->string('name')->nullable(); // human-readable name
            $table->string('subject')->nullable(); // email subject (nullable for SMS)
            // Body supports placeholders: {{borrower_name}}, {{loan_number}}, {{amount}}, {{due_date}}, {{branch_name}}
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event', 'channel']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
