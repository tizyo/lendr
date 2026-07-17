<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('verification_tier', 10)->nullable()->default(null)->after('credit_score_updated_at')
                ->comment('null = no badge | grey = 300-549 | yellow = 550-699 | blue = 700+');
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn('verification_tier');
        });
    }
};
