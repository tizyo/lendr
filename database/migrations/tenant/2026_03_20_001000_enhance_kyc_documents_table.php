<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * For MySQL production: widen the status enum to include under_review.
     * For SQLite (tests): the base migration already includes under_review & expiry_notified_at.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE kyc_documents MODIFY COLUMN status ENUM('pending','under_review','verified','rejected','expired') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE kyc_documents MODIFY COLUMN status ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending'");
        }
    }
};
