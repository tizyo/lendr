<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Anonymous class avoids name collision with the root activity_log migrations.
// Consolidates all three root migrations (create + event column + batch_uuid column)
// into one so fresh tenant databases get the complete schema in a single step.
return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        // Skip if already exists (e.g. test env running both root + tenant migrations on same DB).
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint) {
            $blueprint->bigIncrements('id');
            $blueprint->string('log_name')->nullable();
            $blueprint->text('description');
            $blueprint->nullableMorphs('subject', 'subject');
            $blueprint->string('event')->nullable();
            $blueprint->nullableMorphs('causer', 'causer');
            $blueprint->json('properties')->nullable();
            $blueprint->uuid('batch_uuid')->nullable();
            $blueprint->timestamps();
            $blueprint->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('activitylog.table_name', 'activity_log'));
    }
};
