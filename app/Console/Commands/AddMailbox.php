<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use Illuminate\Console\Command;

class AddMailbox extends Command
{
    protected $signature = 'mail:add';
    protected $description = 'Добавить новый IMAP ящик для сканирования';

    public function handle()
    {
        $this->info('Добавление нового почтового ящика (IMAP)');

        $email = $this->ask('Email адрес (например, you@gmail.com)');
        $host = $this->ask('IMAP сервер (например, imap.gmail.com)', 'imap.gmail.com');
        $port = $this->ask('Порт (по умолчанию 993)', 993);
        $username = $this->ask('Имя пользователя', $email);

        $password = $this->secret('Пароль (будет зашифрован в БД)');

        $encryption = $this->choice('Тип шифрования', ['ssl', 'tls', 'none'], 0);

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

            $this->info("Ящик {$email} успешно добавлен и активирован!");
        } catch (\Exception $e) {
            $this->error("Ошибка при добавлении: " . $e->getMessage());
        }
    }
}
