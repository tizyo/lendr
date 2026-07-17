<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_log', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('table_name', 100)->index();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unsignedBigInteger('new_id')->nullable();
            $table->string('legacy_ref', 100)->nullable();
            $table->enum('status', ['success', 'failed', 'skipped'])->default('success')->index();
            $table->text('notes')->nullable();
            $table->timestamp('migrated_at')->useCurrent();

            $table->index(['tenant_id', 'table_name']);
            $table->index(['tenant_id', 'table_name', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_log');
    }
};
