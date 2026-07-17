<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_agreement_id')->constrained('loan_agreements')->cascadeOnDelete();
            $table->string('event');          // generated, otp_sent, signed, voided, download
            $table->string('actor')->nullable();  // name/phone/email of who triggered
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();  // extra key/value pairs
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_audit_events');
    }
};
