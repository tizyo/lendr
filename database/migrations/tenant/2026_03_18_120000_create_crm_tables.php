<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->decimal('requested_amount', 15, 2)->nullable();
            $table->string('loan_purpose', 255)->nullable();
            $table->enum('source', ['walk_in', 'referral', 'social_media', 'website', 'agent', 'staff', 'campaign', 'other'])->default('walk_in');
            $table->string('referral_name')->nullable();
            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'lost'])->default('new');
            $table->string('lost_reason')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_borrower_id')->nullable()->constrained('borrowers')->nullOnDelete();
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('source');
            $table->index('assigned_to');
            $table->index('follow_up_date');
        });

        Schema::create('borrower_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            $table->foreignId('borrower_id')->nullable()->constrained('borrowers')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->enum('channel', ['call', 'visit', 'email', 'sms', 'whatsapp', 'other']);
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->enum('outcome', ['no_answer', 'left_message', 'spoke_to_borrower', 'meeting_scheduled', 'promise_to_pay', 'declined', 'completed']);
            $table->text('notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->decimal('amount_discussed', 15, 2)->nullable();
            $table->timestamp('interaction_at')->useCurrent();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('borrower_id');
            $table->index('recorded_by');
            $table->index('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_interactions');
        Schema::dropIfExists('leads');
    }
};
