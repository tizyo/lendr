<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_number')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 30)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('national_id', 50)->nullable();
            $table->string('address')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0); // % of disbursed principal
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('fixed_commission', 15, 2)->default(0); // used if type=fixed
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->foreignId('managed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->decimal('disbursed_amount', 15, 2);
            $table->decimal('commission_amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'paid', 'reversed'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('loan_id');
            $table->index('status');
        });

        // Add agent_id to loans
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('loan_group_id')
                  ->constrained('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Tenant\Agent::class);
            $table->dropColumn('agent_id');
        });
        Schema::dropIfExists('agent_commissions');
        Schema::dropIfExists('agents');
    }
};
