<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landlord-side (root DB) table storing one payment wallet per Enterprise tenant.
 * Configured by the superadmin/landlord via the landlord panel.
 * Used by AutoDisburseLoanJob and ProcessAutoDebitJob for credential lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('gateway'); // flutterwave|mtn_momo|airtel_money|pawapay|zamtel_kwacha
            $table->string('environment')->default('sandbox'); // sandbox|production
            $table->text('wallet_id')->nullable();   // provider-side wallet/account ID
            $table->text('api_key')->nullable();     // encrypted primary key/secret
            $table->text('api_secret')->nullable();  // encrypted secondary secret (OAuth)
            $table->text('webhook_secret')->nullable(); // encrypted webhook verification secret
            $table->json('metadata')->nullable();    // provider-specific extras (e.g. airtel_pin, mtn_disbursement_key)
            $table->boolean('disburse_enabled')->default(true);  // Phase 54
            $table->boolean('debit_enabled')->default(false);    // Phase 55
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_wallets');
    }
};
