<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount_requested', 15, 2);
            $table->decimal('interest_rate_offered', 5, 2)->nullable(); // borrower's offered rate
            $table->string('purpose')->nullable();
            $table->integer('tenure_months')->nullable();
            $table->enum('status', ['draft', 'active', 'funded', 'withdrawn', 'expired'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('marketplace_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // investor/lender (staff user)
            $table->decimal('amount_offered', 15, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined', 'withdrawn'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'user_id']); // one interest per lender per listing
            $table->index(['user_id', 'status']);
        });

        Schema::create('marketplace_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'reviewer_id']); // one review per listing per reviewer
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reviews');
        Schema::dropIfExists('marketplace_interests');
        Schema::dropIfExists('marketplace_listings');
    }
};
