<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('document_hash', 64)->nullable();   // SHA-256 of PDF bytes
            $table->string('pdf_path')->nullable();            // storage path
            $table->enum('status', ['pending', 'otp_sent', 'signed', 'voided'])->default('pending');
            $table->string('otp_hash')->nullable();            // bcrypt hash of OTP
            $table->timestamp('otp_expires_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->string('signed_by_name')->nullable();
            $table->string('signed_by_phone')->nullable();
            $table->string('signing_ip')->nullable();
            $table->string('signing_device')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_agreements');
    }
};
