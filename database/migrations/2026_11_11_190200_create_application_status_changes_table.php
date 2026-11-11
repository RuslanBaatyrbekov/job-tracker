<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_id')->nullable()->constrained('emails')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['to_status', 'changed_at']);
        });

        // Seed history with the current state so the funnel has a starting point.
        DB::table('job_applications')->orderBy('id')->each(function ($row) {
            DB::table('application_status_changes')->insert([
                'job_application_id' => $row->id,
                'email_id' => $row->source_email_id,
                'from_status' => null,
                'to_status' => $row->status,
                'changed_at' => $row->applied_at ?? $row->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_changes');
    }
};
