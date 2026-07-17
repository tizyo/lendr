<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Featured repo items — paid promotions & landlord-curated picks.
 *
 * - type = 'paid'   : tenant paid K50/day to feature their item
 * - type = 'manual' : landlord superadmin manually featured the item (free)
 *
 * Max 10 active paid slots per tenant.
 * expires_at drives automatic de-listing via artisan command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_repo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_item_id')->constrained('repo_items')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->enum('type', ['paid', 'manual'])->default('paid');

            // Payment details (null for manual slots)
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->unsignedSmallInteger('days_paid')->nullable();
            $table->string('payment_reference')->nullable(); // external payment ref
            $table->enum('payment_status', ['pending', 'confirmed', 'failed'])->default('pending');

            // Display window
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();  // null = never (manual, indefinite)
            $table->boolean('is_active')->default(false); // activated once payment confirmed

            // Landlord metadata
            $table->unsignedBigInteger('approved_by')->nullable(); // landlord_user_id
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('repo_item_id');
        });

        Schema::create('hot_deals', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('tenant_name');

            // Deal details
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('loan_product')->nullable();       // e.g. "Quick Cash Loan"
            $table->decimal('interest_rate', 5, 2)->nullable(); // monthly %
            $table->decimal('min_amount', 14, 2)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('tenure')->nullable();              // e.g. "3 - 24 months"
            $table->text('requirements')->nullable();          // eligibility notes
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('badge_label')->nullable();         // e.g. "Limited Time", "Best Rate"
            $table->string('image_url', 1024)->nullable();

            // Display window
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();      // null = no expiry

            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('leads_count')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
            $table->index('tenant_id');
        });

        // Leads captured from Hot Deals (contact form submissions)
        Schema::create('hot_deal_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hot_deal_id')->constrained('hot_deals')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('hot_deal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hot_deal_leads');
        Schema::dropIfExists('hot_deals');
        Schema::dropIfExists('featured_repo_items');
    }
};
