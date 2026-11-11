<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use Illuminate\Console\Command;

class AddMailbox extends Command
{
    protected $signature = 'mail:add';

    protected $description = 'Add an IMAP mailbox to scan';

    public function handle()
    {
        $this->info('Add an IMAP mailbox');

        $email = $this->ask('Email address (e.g. you@gmail.com)');
        $host = $this->ask('IMAP host', 'imap.gmail.com');
        $port = $this->ask('Port', 993);
        $username = $this->ask('Username', $email);

        $password = $this->secret('Password / App Password (stored encrypted)');

        $encryption = $this->choice('Encryption', ['ssl', 'tls', 'none'], 0);

        try {
            Mailbox::create([
                'email' => $email,
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'encryption' => $encryption,
                'protocol' => 'imap',
                'is_active' => true,
            ]);

            $this->info("Mailbox {$email} added and activated.");
        } catch (\Exception $e) {
            $this->error('Failed to add mailbox: '.$e->getMessage());
        }
    }
}
