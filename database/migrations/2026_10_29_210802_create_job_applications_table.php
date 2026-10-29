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

            // Связь с оригинальным письмом, из которого ИИ вытащил инфу (nullable, так как отклик можно создать вручную)
            $table->foreignId('source_email_id')->nullable()->constrained('emails')->nullOnDelete();

            $table->string('company_name');
            $table->string('position');

            // applied, rejected, interview, offer
            $table->string('status')->default('applied');

            $table->string('salary_range')->nullable();
            $table->text('job_description')->nullable(); // Оригинальное описание вакансии (если есть)
            $table->timestamp('applied_at')->nullable();

            // Поле для хранения сырого ответа ИИ или доп. метаданных (чтобы было легко расширять без новых миграций)
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
