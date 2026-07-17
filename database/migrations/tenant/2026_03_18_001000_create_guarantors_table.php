<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->string('name');
            $table->string('national_id', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('relationship', 64)->nullable();   // spouse, parent, employer, friend, etc.
            $table->string('employer', 255)->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();
            $table->index('loan_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantors');
    }
};
