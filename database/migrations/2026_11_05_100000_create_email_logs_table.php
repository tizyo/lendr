<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every email LENDR sends to a workspace's owner or staff (not to borrowers), so
 * support can see what went out, whether it arrived, and send it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('to_email')->index();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->string('mailable')->nullable();          // class basename, e.g. TenantWelcomeMail
            $table->string('context', 16)->default('platform'); // platform | tenant (who it was sent as)
            $table->string('status', 32)->default('sending');   // sending | sent | delivered | bounced | failed | …
            $table->string('provider', 16)->nullable();          // resend | other
            $table->string('provider_id')->nullable()->index();  // Resend email id, for delivery status
            $table->text('error')->nullable();
            $table->longText('html')->nullable();                // kept so it can be viewed and sent again
            $table->longText('text')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->boolean('resendable')->default(true);
            $table->string('sent_by')->nullable();               // landlord email for support-sent mail
            $table->foreignId('resent_from_id')->nullable()->constrained('email_logs')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
