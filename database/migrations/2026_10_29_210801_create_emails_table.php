<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_emails_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();

            // Unique id of the message on the IMAP server (uid)
            // Composite unique key (mailbox_id + imap_uid) prevents duplicates
            $table->string('imap_uid');

            $table->string('subject')->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->timestamp('date'); // when the email was sent

            // Processing status, see App\Enums\EmailStatus
            $table->string('status')->default('pending_subject_scan');

            // Body is downloaded only for emails that passed the subject filter
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            $table->timestamps();

            // Fast lookup of unprocessed emails
            $table->unique(['mailbox_id', 'imap_uid']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
