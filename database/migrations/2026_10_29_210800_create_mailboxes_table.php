<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_mailboxes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('host');
            $table->integer('port')->default(993);
            $table->string('username');
            $table->text('password'); // encrypted App Password
            $table->string('protocol')->default('imap');
            $table->string('encryption')->default('ssl'); // ssl or tls
            $table->timestamp('last_synced_at')->nullable(); // last successful sync
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
    }
};
