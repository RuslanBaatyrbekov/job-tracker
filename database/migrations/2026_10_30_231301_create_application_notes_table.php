<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate of 2026_10_29_210803_create_application_notes_table.
 * Kept (guarded) so databases that already ran it stay consistent,
 * while fresh installs and the test suite no longer fail with "table already exists".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('application_notes')) {
            return;
        }

        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('type');
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // The table belongs to the original migration.
    }
};
