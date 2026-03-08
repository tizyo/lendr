<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique(); // LENDR-generated ref e.g. LN-2024-000001
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();

            // Loan amounts — stored as strings via BCMath to avoid float imprecision
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('processing_fee', 15, 2)->default(0);
            $table->decimal('insurance_fee', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->decimal('penalty_balance', 15, 2)->default(0);

            // Terms (snapshotted at origination — loan plan can change later)
            $table->decimal('interest_rate', 8, 4);
            $table->string('interest_type');
            $table->string('interest_period');
            $table->integer('tenure');
            $table->string('tenure_type');
            $table->string('repayment_schedule');
            $table->decimal('penalty_rate', 8, 4)->default(0);
            $table->integer('grace_period_days')->default(0);

            // Disbursement
            $table->enum('disbursement_method', ['cash', 'bank_transfer', 'airtel_money', 'mtn_momo', 'zamtel_kwacha'])->nullable();
            $table->string('disbursement_account')->nullable(); // phone or bank acc
            $table->string('disbursement_reference')->nullable();
            $table->string('currency')->default('ZMW');

            // Status & dates
            $table->string('status')->default('submitted');
            $table->date('application_date');
            $table->date('approval_date')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->date('first_repayment_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('closed_date')->nullable();

            // Collateral & guarantor
            $table->text('collateral_description')->nullable();
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_phone')->nullable();
            $table->string('guarantor_relationship')->nullable();

            // Purpose
            $table->text('loan_purpose')->nullable();

            // Mobile money tracking
            $table->string('momo_transaction_id')->nullable();
            $table->string('momo_provider')->nullable();

            // Migration metadata
            $table->string('legacy_loan_id')->nullable();
            $table->string('migration_source')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['borrower_id', 'status']);
            $table->index('status');
            $table->index('loan_number');
            $table->index('disbursement_date');
            $table->index('maturity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
