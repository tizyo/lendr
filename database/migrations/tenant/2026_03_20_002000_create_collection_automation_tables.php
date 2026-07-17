<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('dpd_threshold');             // Days Past Due to trigger
            $table->enum('action', ['assign_collector', 'field_visit', 'legal_action', 'write_off_notice']);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'dpd_threshold']);
        });

        Schema::create('collection_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalation_rule_id')->nullable()->constrained('escalation_rules')->nullOnDelete();
            $table->enum('status', ['open', 'promised', 'escalated', 'resolved', 'closed'])->default('open');
            $table->enum('action_taken', ['assign_collector', 'field_visit', 'legal_action', 'write_off_notice'])->nullable();
            $table->unsignedInteger('dpd_at_creation')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['status', 'assigned_to']);
        });

        Schema::create('promise_to_pays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_case_id')->constrained('collection_cases')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('promise_date');
            $table->decimal('promise_amount', 15, 2);
            $table->enum('status', ['pending', 'kept', 'broken'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promise_to_pays');
        Schema::dropIfExists('collection_cases');
        Schema::dropIfExists('escalation_rules');
    }
};
