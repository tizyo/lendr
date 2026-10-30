<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Each column is its own ALTER on MySQL, so a failed run can leave only the first.
            if (! Schema::hasColumn('loans', 'fx_rate')) {
                $table->decimal('fx_rate', 15, 6)->default(1.000000)->after('currency');
            }
            if (! Schema::hasColumn('loans', 'base_currency')) {
                $table->string('base_currency', 10)->default('ZMW')->after('fx_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['fx_rate', 'base_currency']);
        });
    }
};
