<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of Accounts
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // e.g. 1001, 2001, 4001
            $table->string('name');                         // e.g. "Cash", "Loans Receivable"
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Journal Entries (one entry = one balanced transaction)
        Schema::create('gl_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // e.g. JNL-2026-0001
            $table->date('entry_date');
            $table->text('description');
            $table->string('source_type')->nullable();     // e.g. App\Models\Tenant\Loan
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        // Journal Lines (debits and credits)
        Schema::create('gl_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('gl_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('gl_accounts')->restrictOnDelete();
            $table->enum('side', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_journal_lines');
        Schema::dropIfExists('gl_journal_entries');
        Schema::dropIfExists('gl_accounts');
    }
};
