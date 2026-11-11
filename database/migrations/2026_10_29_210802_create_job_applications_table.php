<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_job_applications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();

            // Email the AI extracted the data from (nullable: applications can be created manually)
            $table->foreignId('source_email_id')->nullable()->constrained('emails')->nullOnDelete();

            $table->string('company_name');
            $table->string('position');

            // applied, rejected, interview, offer
            $table->string('status')->default('applied');

            $table->string('salary_range')->nullable();
            $table->text('job_description')->nullable(); // original vacancy text, if known
            $table->timestamp('applied_at')->nullable();

            // Raw AI answer and extra metadata (extendable without new migrations)
            $table->json('ai_metadata')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
