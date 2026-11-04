<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments that can be undone exactly:
 *  - payment_allocations records which instalment each part of a payment went to
 *    (interest, fee, principal), so a reversal restores the schedule precisely;
 *  - payments gain the money a payment couldn't apply (an overpayment, held as a
 *    customer credit) and who reversed it, when and why.
 * Guarded so a half-applied run can be retried.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('loan_schedule_id')->constrained('loan_schedules')->cascadeOnDelete();
                $table->decimal('interest', 15, 2)->default(0);
                $table->decimal('fee', 15, 2)->default(0);
                $table->decimal('principal', 15, 2)->default(0);
                $table->timestamps();

                $table->index('payment_id');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'unallocated_amount')) {
                $table->decimal('unallocated_amount', 15, 2)->default(0)->after('fee_allocated');
            }
            if (! Schema::hasColumn('payments', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable();
            }
            if (! Schema::hasColumn('payments', 'reversal_reason')) {
                $table->string('reversal_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');

        $columns = array_values(array_filter(
            ['unallocated_amount', 'reversed_at', 'reversed_by', 'reversal_reason'],
            fn ($c) => Schema::hasColumn('payments', $c),
        ));

        if ($columns) {
            Schema::table('payments', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
