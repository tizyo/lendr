<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->index();
            $table->string('subject', 255);
            $table->text('message');
            $table->string('type', 32)->default('support');       // support|bug|feature
            $table->string('status', 32)->default('open');         // open|in_progress|resolved|closed
            $table->string('priority', 16)->default('medium');     // low|medium|high|critical
            $table->string('submitted_by', 128)->nullable();       // name of submitting user
            $table->string('submitted_by_email', 128)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('author_type', 16)->default('tenant');  // tenant|landlord
            $table->string('author_name', 128)->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
