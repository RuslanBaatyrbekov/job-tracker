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

            // Уникальный ID письма с сервера IMAP (обычно message-id или uid)
            // Composite unique key (mailbox_id + imap_uid) предотвратит дублирование
            $table->string('imap_uid');

            $table->string('subject')->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->timestamp('date'); // Дата отправки письма

            // Статус обработки: pending_subject_scan, irrelevant, pending_body_analysis, processed
            $table->string('status')->default('pending_subject_scan');

            // Тело письма докачиваем только если статус сменился на pending_body_analysis
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            $table->timestamps();

            // Индексы для ускорения поиска непроверенных писем
            $table->unique(['mailbox_id', 'imap_uid']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
