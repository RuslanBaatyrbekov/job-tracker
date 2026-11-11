<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Services\ImapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

class ImapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_headers_and_creates_emails()
    {
        $mailbox = Mailbox::create([
            'email' => 'test@test.com',
            'host' => 'imap.test.com',
            'username' => 'test',
            'password' => 'secret',
        ]);

        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getUid')->andReturn('uid-12345');
        $mockMessage->shouldReceive('getSubject')->andReturn(['Test Job Offer']);

        $mockFrom = new \stdClass;
        $mockFrom->mail = 'hr@company.com';
        $mockFrom->personal = 'HR Manager';

        $mockMessage->shouldReceive('getFrom')->andReturn([$mockFrom]);
        $mockMessage->shouldReceive('getDate')->andReturn(now());

        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('all')->andReturnSelf();
        $mockQuery->shouldReceive('leaveUnread')->andReturnSelf();
        $mockQuery->shouldReceive('setFetchOrder')->with('desc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();

        // Must be a MessageCollection, not a plain collect()
        $messageCollection = MessageCollection::make([$mockMessage]);
        $mockQuery->shouldReceive('get')->andReturn($messageCollection);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('connect')->once();
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('disconnect')->once();

        $mockClientManager = Mockery::mock(ClientManager::class);
        $mockClientManager->shouldReceive('make')->once()->andReturn($mockClient);

        $imapService = new ImapService($mockClientManager);

        $count = $imapService->syncHeaders($mailbox);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('emails', [
            'mailbox_id' => $mailbox->id,
            'imap_uid' => 'uid-12345',
            'subject' => 'Test Job Offer',
            'from_address' => 'hr@company.com',
            'status' => 'pending_subject_scan',
        ]);
    }
}
