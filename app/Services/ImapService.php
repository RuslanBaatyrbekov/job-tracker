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
            'host'          => $mailbox->host,
            'port'          => $mailbox->port,
            'encryption'    => $mailbox->encryption,
            'validate_cert' => false, // Для тестов лучше false
            'username'      => $mailbox->username,
            'password'      => $mailbox->password,
            'protocol'      => $mailbox->protocol
        ]);

        $client->connect();
        return $client;
    }

    /**
     * Этап 1: Скачивание только заголовков писем (для экономии токенов и трафика).
     */
    public function syncHeaders(Mailbox $mailbox): int
    {
        $count = 0;
        try {
            $client = $this->getClient($mailbox);
            $folder = $client->getFolder('INBOX'); // Берем папку Входящие

            // Формируем запрос: ищем письма, пришедшие после последней синхронизации
            $query = $folder->query();
            if ($mailbox->last_synced_at) {
                // IMAP использует специфичный формат даты
                $query->since($mailbox->last_synced_at->format('d M Y'));
            }

            $messages = $query->leaveUnread()->get(['FLAGS', 'INTERNALDATE', 'ENVELOPE']);

            foreach ($messages as $message) {
                $uid = $message->getUid();

                // Проверяем, нет ли уже такого письма в базе
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
                }
            }

            // Обновляем время синхронизации
            $mailbox->update(['last_synced_at' => now()]);

            $client->disconnect();

        } catch (\Exception $e) {
            Log::error("Ошибка синхронизации ящика {$mailbox->email}: " . $e->getMessage());
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
