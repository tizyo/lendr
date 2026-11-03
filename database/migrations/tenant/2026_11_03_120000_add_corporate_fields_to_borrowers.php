<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corporate borrowers (SMEs, cooperatives, NGOs) alongside individuals.
 * The PACRA registration number and TPIN already live in company_reg_number / tpin_number.
 * Each column is guarded so a half-applied run can be retried (MySQL DDL isn't transactional).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'borrower_type', 'company_name', 'trading_name', 'business_type', 'industry', 'incorporation_date',
        'contact_person_name', 'contact_person_position', 'contact_person_phone', 'contact_person_email',
    ];

    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            if (! Schema::hasColumn('borrowers', 'borrower_type')) {
                $table->string('borrower_type', 20)->default('individual')->after('borrower_number')->index();
            }
            if (! Schema::hasColumn('borrowers', 'company_name')) {
                $table->string('company_name')->nullable()->after('borrower_type');
            }
            if (! Schema::hasColumn('borrowers', 'trading_name')) {
                $table->string('trading_name')->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('borrowers', 'business_type')) {
                $table->string('business_type', 40)->nullable()->after('trading_name');
            }
            if (! Schema::hasColumn('borrowers', 'industry')) {
                $table->string('industry', 100)->nullable()->after('business_type');
            }
            if (! Schema::hasColumn('borrowers', 'incorporation_date')) {
                $table->date('incorporation_date')->nullable()->after('industry');
            }
            if (! Schema::hasColumn('borrowers', 'contact_person_name')) {
                $table->string('contact_person_name', 150)->nullable()->after('next_of_kin_relationship');
            }
            if (! Schema::hasColumn('borrowers', 'contact_person_position')) {
                $table->string('contact_person_position', 100)->nullable()->after('contact_person_name');
            }
            if (! Schema::hasColumn('borrowers', 'contact_person_phone')) {
                $table->string('contact_person_phone', 20)->nullable()->after('contact_person_position');
            }
            if (! Schema::hasColumn('borrowers', 'contact_person_email')) {
                $table->string('contact_person_email')->nullable()->after('contact_person_phone');
            }
        });
    }

    public function down(): void
    {
        $existing = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('borrowers', $c)));

        if (in_array('borrower_type', $existing, true)) {
            Schema::table('borrowers', fn (Blueprint $table) => $table->dropIndex(['borrower_type']));
        }

        if ($existing) {
            Schema::table('borrowers', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
