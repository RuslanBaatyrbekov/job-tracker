<?php

namespace App\Services;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Models\Mailbox;
use App\Support\HtmlText;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapService
{
    public function __construct(protected ClientManager $clientManager) {}

    protected function getClient(Mailbox $mailbox): Client
    {
        $client = $this->clientManager->make([
            'host' => trim($mailbox->host),
            'port' => (int) trim((string) $mailbox->port),
            'encryption' => $mailbox->encryption === 'none' ? false : ($mailbox->encryption ?: 'ssl'), // Gmail expects lowercase
            'validate_cert' => true,
            'username' => trim($mailbox->username),
            'password' => trim($mailbox->password),
            'protocol' => 'imap',
            'authentication' => 'login', // force plain LOGIN (App Password), not OAuth
        ]);

        $client->connect();

        return $client;
    }

    /**
     * Stage 1: download headers only (saves tokens and traffic).
     */
    public function syncHeaders(Mailbox $mailbox, int $limit = 50): int
    {
        $count = 0;

        try {
            Log::info("IMAP sync started for {$mailbox->email} (limit: {$limit})");

            $client = $this->getClient($mailbox);
            $query = $client->getFolder('INBOX')->query();

            // Regular run: everything since the last sync (IMAP SINCE has day precision, duplicates are skipped below).
            // Manual run with a big limit: just take the latest N emails.
            if ($limit <= 50 && $mailbox->last_synced_at) {
                $query->since($mailbox->last_synced_at);
                // Don't lose emails if more than $limit arrived since the last run.
                $limit = max($limit, 200);
            } else {
                $query->all();
            }

            $messages = $query->leaveUnread()
                ->setFetchOrder('desc')
                ->limit($limit)
                ->get();

            Log::info('IMAP messages found: '.$messages->count());

            foreach ($messages as $message) {
                $uid = $message->getUid();

                $exists = Email::where('mailbox_id', $mailbox->id)
                    ->where('imap_uid', $uid)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Email::create([
                    'mailbox_id' => $mailbox->id,
                    'imap_uid' => $uid,
                    'subject' => $message->getSubject()[0] ?? 'No Subject',
                    'from_address' => $message->getFrom()[0]->mail ?? 'unknown@example.com',
                    'from_name' => $message->getFrom()[0]->personal ?? null,
                    'date' => $message->getDate(),
                    'status' => EmailStatus::PendingSubjectScan,
                ]);
                $count++;
            }

            $mailbox->update(['last_synced_at' => now()]);
            Log::info("IMAP sync finished for {$mailbox->email}, new emails: {$count}");

            $client->disconnect();
        } catch (\Exception $e) {
            Log::error("IMAP sync failed for {$mailbox->email}: ".$e->getMessage());
        }

        return $count;
    }

    /**
     * Stage 2: download the body of one email (only for emails that passed the subject filter).
     */
    public function fetchBodyForEmail(Email $email): bool
    {
        try {
            $client = $this->getClient($email->mailbox);
            $message = $client->getFolder('INBOX')->query()->getMessageByUid($email->imap_uid);

            if ($message) {
                $bodyHtml = $message->getHTMLBody();
                $bodyText = $message->getTextBody();

                $email->update([
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText ?: HtmlText::fromHtml($bodyHtml),
                ]);

                $client->disconnect();

                return true;
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch body of email {$email->id}: ".$e->getMessage());
        }

        return false;
    }
}
