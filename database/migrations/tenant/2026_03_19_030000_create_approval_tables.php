<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workflow definitions
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                              // e.g. "Loan Disbursement > 50K"
            $table->string('entity_type');                                       // e.g. "loan_disbursement"
            $table->decimal('min_amount', 15, 2)->nullable();                    // trigger threshold
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->json('required_roles');                                      // ["BranchManager","SuperAdmin"]
            $table->unsignedTinyInteger('required_approvals')->default(1);       // how many approvers needed
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Approval requests (one per entity needing approval)
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->string('entity_type');                                       // e.g. "loan_disbursement"
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
        });

        // Individual approval/rejection actions
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->enum('action', ['approve', 'reject']);
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflows');
    }
};
