<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('group_number')->unique();
            $table->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('meeting_schedule')->nullable(); // e.g. "Every Monday 10AM"
            $table->string('meeting_location')->nullable();
            $table->enum('status', ['active', 'inactive', 'dissolved'])->default('active');
            $table->unsignedInteger('max_members')->default(30);
            $table->timestamps();
            $table->softDeletes();

            $table->index('officer_id');
            $table->index('status');
        });

        Schema::create('loan_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_group_id')->constrained('loan_groups')->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->enum('role', ['leader', 'secretary', 'member'])->default('member');
            $table->boolean('is_active')->default(true);
            $table->date('joined_date');
            $table->date('left_date')->nullable();
            $table->timestamps();

            $table->unique(['loan_group_id', 'borrower_id']);
            $table->index('loan_group_id');
            $table->index('borrower_id');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('loan_group_id')->nullable()->after('borrower_id')
                  ->constrained('loan_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Tenant\LoanGroup::class);
            $table->dropColumn('loan_group_id');
        });
        Schema::dropIfExists('loan_group_members');
        Schema::dropIfExists('loan_groups');
    }
};
