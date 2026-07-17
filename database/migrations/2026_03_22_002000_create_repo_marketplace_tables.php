<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repossessed-items marketplace.
 *
 * Tenants (Growth+ plan) post collateral/repossessed items.
 * Ghost users browse and enquire on items.
 * All tables are in the central DB for cross-tenant visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Items ────────────────────────────────────────────────────────────
        Schema::create('repo_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('tenant_name');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 18, 2);
            $table->decimal('original_value', 18, 2)->nullable();
            $table->enum('category', [
                'furniture', 'electronics', 'vehicle', 'land', 'equipment', 'other',
            ])->default('other');
            $table->enum('condition', ['new', 'good', 'fair', 'poor'])->default('good');
            $table->string('location')->nullable();
            $table->boolean('is_sold')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('enquiries_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['is_active', 'is_sold']);
            $table->index('category');
        });

        // ── Images (up to N per item) ─────────────────────────────────────
        Schema::create('repo_item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('repo_items')->cascadeOnDelete();
            $table->string('image_url', 1024);
            $table->string('caption')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'is_primary']);
        });

        // ── Enquiries (ghost users → item owners) ────────────────────────
        Schema::create('repo_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('repo_items')->cascadeOnDelete();
            $table->foreignId('ghost_user_id')->constrained('ghost_users')->cascadeOnDelete();
            $table->text('message');
            $table->enum('status', ['new', 'read', 'replied'])->default('new');
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index('ghost_user_id');
        });

        // ── Cart (ghost users save items to review) ───────────────────────
        Schema::create('repo_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ghost_user_id')->constrained('ghost_users')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('repo_items')->cascadeOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['ghost_user_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_carts');
        Schema::dropIfExists('repo_enquiries');
        Schema::dropIfExists('repo_item_images');
        Schema::dropIfExists('repo_items');
    }
};
