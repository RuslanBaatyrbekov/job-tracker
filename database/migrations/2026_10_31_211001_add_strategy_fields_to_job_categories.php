<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_categories', function (Blueprint $table) {
            $table->longText('resume_text')->nullable()->after('color');
            $table->longText('ai_strategy_report')->nullable()->after('resume_text');
            $table->timestamp('last_analyzed_at')->nullable()->after('ai_strategy_report');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_categories', function (Blueprint $table) {
            //
        });
    }
};
