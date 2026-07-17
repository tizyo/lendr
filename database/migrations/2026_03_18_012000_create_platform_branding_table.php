<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_branding', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 128)->default('LENDR');
            $table->string('tagline', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_path', 512)->nullable();      // public disk path
            $table->string('favicon_path', 512)->nullable();   // public disk path
            $table->string('primary_color', 7)->default('#059669'); // hex
            $table->text('invoice_footer')->nullable();        // appears on PDF receipts/invoices
            $table->text('email_footer')->nullable();          // appears in email footers
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_branding');
    }
};
