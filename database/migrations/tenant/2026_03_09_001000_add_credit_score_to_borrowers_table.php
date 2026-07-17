<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_score')->nullable()->after('kyc_verified');
            $table->timestamp('credit_score_updated_at')->nullable()->after('credit_score');
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn(['credit_score', 'credit_score_updated_at']);
        });
    }
};
