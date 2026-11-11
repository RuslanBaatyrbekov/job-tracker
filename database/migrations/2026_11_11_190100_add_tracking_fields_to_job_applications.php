<?php

use App\Support\CompanyName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            // What we sent and where — needed to see which channel / resume actually converts.
            $table->string('source')->nullable()->after('status');
            $table->text('vacancy_url')->nullable()->after('source');
            $table->string('location')->nullable()->after('vacancy_url');
            $table->string('work_mode')->nullable()->after('location');
            $table->string('language', 5)->nullable()->after('work_mode');
            $table->string('german_level')->nullable()->after('language');
            $table->boolean('cover_letter_sent')->default(false)->after('german_level');

            // Matching the same company across different emails
            $table->string('company_key')->nullable()->after('company_name')->index();
            $table->string('company_domain')->nullable()->after('company_key')->index();

            // Follow-ups
            $table->timestamp('last_activity_at')->nullable()->after('applied_at');
            $table->timestamp('follow_up_reminded_at')->nullable()->after('last_activity_at');
            $table->timestamp('follow_up_sent_at')->nullable()->after('follow_up_reminded_at');

            $table->foreignId('job_lead_id')->nullable()->after('source_email_id')->constrained('job_leads')->nullOnDelete();
        });

        // Columns were never written: everything goes to application_notes.
        if (Schema::hasColumn('job_applications', 'rejection_reason')) {
            Schema::table('job_applications', function (Blueprint $table) {
                $table->dropColumn(['rejection_reason', 'ai_advice']);
            });
        }

        DB::table('job_applications')->orderBy('id')->each(function ($row) {
            DB::table('job_applications')->where('id', $row->id)->update([
                'company_key' => CompanyName::key($row->company_name),
                'last_activity_at' => $row->updated_at ?? $row->applied_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_lead_id');
            $table->dropIndex(['company_key']);
            $table->dropIndex(['company_domain']);
            $table->dropColumn([
                'source', 'vacancy_url', 'location', 'work_mode', 'language', 'german_level', 'cover_letter_sent',
                'company_key', 'company_domain', 'last_activity_at', 'follow_up_reminded_at', 'follow_up_sent_at',
            ]);
            $table->text('rejection_reason')->nullable();
            $table->text('ai_advice')->nullable();
        });
    }
};
