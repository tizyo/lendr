<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GPS check-ins by field officers
        Schema::create('field_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamps();
        });

        // Cash / mobile-money collections by field officers
        Schema::create('field_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('collection_method')->default('cash'); // cash | mobile_money
            $table->string('reference_number')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('receipt_number')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('collected_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Offline sync queue — items captured offline, submitted on reconnect
        Schema::create('offline_sync_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action'); // check_in | collect_payment
            $table->json('payload');
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_queue');
        Schema::dropIfExists('field_collections');
        Schema::dropIfExists('field_check_ins');
    }
};
