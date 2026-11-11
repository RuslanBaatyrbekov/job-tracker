<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_email_id')->nullable()->constrained('emails')->nullOnDelete();
            $table->foreignId('job_category_id')->nullable()->constrained('job_categories')->nullOnDelete();

            $table->string('source')->default('other');
            $table->string('title');
            $table->string('company_name')->nullable();
            $table->string('company_key')->nullable()->index();
            $table->string('location')->nullable();
            $table->text('url')->nullable();
            // Hash of the normalized URL (or title+company) to avoid saving the same vacancy twice.
            $table->string('fingerprint', 64)->unique();
            $table->text('description')->nullable();

            // AI scoring
            $table->unsignedTinyInteger('score')->nullable()->index();
            $table->text('score_reason')->nullable();
            $table->json('red_flags')->nullable();
            $table->string('work_mode')->nullable();
            $table->string('german_level')->nullable();
            $table->string('language', 5)->nullable();
            $table->timestamp('scored_at')->nullable();

            // Cover letter draft, tailored bullets, missing keywords (markdown)
            $table->longText('tailoring')->nullable();

            $table->string('status')->default('new')->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_leads');
    }
};
