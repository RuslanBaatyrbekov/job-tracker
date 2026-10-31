<?php

namespace App\Services;

use App\Models\Mailbox;
use App\Models\Email;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Illuminate\Support\Facades\Log;

class ImapService
{
    public function __construct(protected ClientManager $clientManager)
    {
    }

    protected function getClient(Mailbox $mailbox): Client
    {
        $client = $this->clientManager->make([
            'host'           => trim($mailbox->host),
            'port'           => (int) trim($mailbox->port),
            'encryption'     => 'ssl', // Gmail требует строчными буквами
            'validate_cert'  => true,  // Для Gmail лучше включить проверку сертификата
            'username'       => trim($mailbox->username),
            'password'       => trim($mailbox->password),
            'protocol'       => 'imap',
            'authentication' => 'login', // Жестко заставляем пакет использовать обычный логин
        ]);

        $client->connect();
        return $client;
    }

    /**
     * Этап 1: Скачивание только заголовков писем (для экономии токенов и трафика).
     */
    public function syncHeaders(Mailbox $mailbox, int $limit = 50): int
    {
        $count = 0;
        try {
            \Log::info("=== Старт синхронизации ящика {$mailbox->email} (Лимит: {$limit}) ===");

            $client = $this->getClient($mailbox);
            \Log::info("1. Успешное подключение к IMAP. Выбираем папку INBOX...");

            $folder = $client->getFolder('INBOX');

            $query = $folder->query();

            // Если лимит стандартный (50 и ниже) и есть дата последней синхронизации — используем её.
            // Если передан большой лимит (ручной запуск), забираем свежие письма с конца без оглядки на дату.
            if ($limit <= 50 && $mailbox->last_synced_at) {
                \Log::info("2. Фильтр: ищем письма после " . $mailbox->last_synced_at->format('Y-m-d H:i:s'));
                $query->since($mailbox->last_synced_at);
            } else {
                \Log::info("2. Фильтр: ручной режим / большой лимит, берем последние {$limit} писем.");
                $query->all();
            }

            \Log::info("3. Выполняем запрос к серверу...");

            // Сортируем от новых к старым и применяем переданный лимит
            $messages = $query->leaveUnread()
                ->setFetchOrder('desc')
                ->limit($limit)
                ->get();

            \Log::info("4. Найдено писем по запросу: " . $messages->count());

            foreach ($messages as $message) {
                $uid = $message->getUid();

                $exists = Email::where('mailbox_id', $mailbox->id)
                    ->where('imap_uid', $uid)
                    ->exists();

                if (!$exists) {
                    Email::create([
                        'mailbox_id'   => $mailbox->id,
                        'imap_uid'     => $uid,
                        'subject'      => $message->getSubject()[0] ?? 'No Subject',
                        'from_address' => $message->getFrom()[0]->mail ?? 'unknown@example.com',
                        'from_name'    => $message->getFrom()[0]->personal ?? null,
                        'date'         => $message->getDate(),
                        'status'       => 'pending_subject_scan',
                    ]);
                    $count++;
                    \Log::info(" [+] Добавлено новое письмо UID: {$uid}");
                } else {
                    \Log::info(" [-] Письмо UID: {$uid} уже есть в базе, пропускаем.");
                }
            }

            $mailbox->update(['last_synced_at' => now()]);
            \Log::info("=== Синхронизация завершена. Всего добавлено: {$count} ===");

            $client->disconnect();

        } catch (\Exception $e) {
            \Log::error("Ошибка синхронизации ящика {$mailbox->email}: " . $e->getMessage());
        }

        return $count;
    }

    /**
     * Этап 2: Докачивание тела конкретного письма (для тех, что прошли ИИ-фильтр по заголовкам)
     */
    public function fetchBodyForEmail(Email $email): bool
    {
        try {
            $client = $this->getClient($email->mailbox);
            $folder = $client->getFolder('INBOX');

            // Получаем конкретное письмо по его UID
            $message = $folder->query()->getMessageByUid($email->imap_uid);

            if ($message) {
                // Если есть HTML-версия, берем ее, иначе текстовую
                $bodyHtml = $message->getHTMLBody();
                $bodyText = $message->getTextBody();

                $email->update([
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText ?: strip_tags($bodyHtml),
                ]);
                return true;
            }
        } catch (\Exception $e) {
            Log::error("Ошибка получения тела письма {$email->id}: " . $e->getMessage());
        }

        return false;
    }
}
