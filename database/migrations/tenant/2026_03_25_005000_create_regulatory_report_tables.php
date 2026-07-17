<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scheduled regulatory report configs
        Schema::create('regulatory_report_configs', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');          // car | liquidity | large_exposure | par
            $table->string('name');
            $table->string('frequency');            // monthly | quarterly | on_demand
            $table->string('recipient_emails');     // comma-separated
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // Generated report records
        Schema::create('regulatory_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');
            $table->string('period');               // e.g. '2026-03', '2026-Q1'
            $table->json('data');
            $table->string('generated_by')->nullable();
            $table->boolean('emailed')->default(false);
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_reports');
        Schema::dropIfExists('regulatory_report_configs');
    }
};
