<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('account_number')->unique();
            $table->enum('type', ['regular', 'fixed', 'target'])->default('regular');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0); // annual %
            $table->date('maturity_date')->nullable();          // for fixed deposits
            $table->decimal('target_amount', 15, 2)->nullable(); // for target savings
            $table->enum('status', ['active', 'dormant', 'closed'])->default('active');
            $table->date('opened_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('borrower_id');
            $table->index('status');
        });

        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'interest', 'fee', 'reversal']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index('savings_account_id');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
        Schema::dropIfExists('savings_accounts');
    }
};
