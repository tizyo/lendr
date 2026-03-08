<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row fund balance per tenant — accessed with locking
        Schema::create('fund_balance', function (Blueprint $table) {
            $table->id();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('total_deposits', 15, 2)->default(0);
            $table->decimal('total_disbursed', 15, 2)->default(0);
            $table->decimal('total_repaid', 15, 2)->default(0);
            $table->decimal('total_penalties', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('available_balance', 15, 2)->default(0); // computed: deposits - disbursed + repaid + penalties - expenses
            $table->string('currency')->default('ZMW');
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();
        });

        // Capital deposit records
        Schema::create('fund_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('source'); // investor name, capital injection description
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque'])->default('bank_transfer');
            $table->string('bank_reference')->nullable();
            $table->date('deposit_date');
            $table->text('notes')->nullable();
            $table->foreignId('deposited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('deposit_date');
            $table->index('status');
        });

        // Immutable ledger — every balance-affecting event is logged here
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_ref')->unique();
            $table->string('type'); // FundTransactionType enum value
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->morphs('source'); // loan payment, deposit, expense, etc.
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
        Schema::dropIfExists('fund_deposits');
        Schema::dropIfExists('fund_balance');
    }
};
