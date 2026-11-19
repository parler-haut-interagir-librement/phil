<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Override;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use Phil\ConversationBundle\Tests\Functional\App\TestKernel;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The bundle in an application: a member writes to another through the page, the message sealed by the KeyManagement
 * component and kept on SQLite, the other reads it in the inbox and the conversation, replies, mutes, blocks and
 * leaves; a conversation of others is not found; the forms refuse a request without the token.
 */
#[CoversNothing]
final class ConversationBundleTest extends WebTestCase
{
    private const array SAME_SITE = ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/messages/'];

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        Store::create($connection);
    }

    public function testAMemberWritesToAnotherWhoReadsAndReplies(): void
    {
        $crawler = $this->client->request('GET', '/messages/new/bob', server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Write to bob');

        $form = $crawler->filter('form.phil-conversation__reply')->form(['body' => 'Hello Bob, game on Friday?']);
        $this->client->submit($form, [], ['HTTP_X_USER' => 'ada'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(303);
        $thread = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/messages/[0-9a-f-]{36}$#', $thread);

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->assertInstanceOf(Connection::class, $connection);
        $body = $connection->fetchOne('SELECT body FROM phil_conversation_message');
        self::assertIsString($body);
        self::assertStringStartsWith('pcv1.a256gcm.', $body);

        $this->client->request('GET', '/messages/', server: ['HTTP_X_USER' => 'bob']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-conversation__summary', 'One unread conversation');
        self::assertSelectorTextContains('.phil-conversation__entry.is-unread [data-slot="badge"]', 'One new');
        self::assertSame('no-store, private', $this->client->getResponse()->headers->get('Cache-Control'));

        $crawler = $this->client->request('GET', $thread, server: ['HTTP_X_USER' => 'bob']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'ada');
        self::assertSelectorTextContains('.phil-conversation__body', 'Hello Bob, game on Friday?');
        self::assertSelectorExists('textarea[data-slot="textarea"][name="body"]');

        $this->client->request('GET', '/messages/', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('.phil-conversation__summary', 'No unread conversation');

        $form = $crawler->filter('form.phil-conversation__reply')->form(['body' => 'Yes!']);
        $this->client->submit($form, [], ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseRedirects($thread, 303);

        $this->client->request('GET', $thread.'/messages', server: ['HTTP_X_USER' => 'ada']);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.phil-conversation__message');
        self::assertSelectorTextContains('.phil-conversation__message:last-child .phil-conversation__body', 'Yes!');
    }

    public function testAnEmptyOrRefusedMessageIsSaidOnThePage(): void
    {
        $this->client->request('POST', '/messages/new/bob', ['_token' => 'csrf-token', 'body' => '  '], server: ['HTTP_X_USER' => 'ada'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'A message says something: its body is empty.');

        $this->client->request('POST', '/messages/settings', ['_token' => 'csrf-token', 'action' => 'allow', 'who_can_write' => 'nobody'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseRedirects('/messages/settings', 303);
        $this->client->request('POST', '/messages/new/bob', ['_token' => 'csrf-token', 'body' => 'Hi'], server: ['HTTP_X_USER' => 'ada'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'This participant does not receive messages from you.');
        self::assertSelectorTextContains('textarea', 'Hi');

        $id = $this->direct('ada', 'cy', 'Hi');
        $this->client->request('POST', '/messages/'.$id.'/reply', ['_token' => 'csrf-token', 'body' => ''], server: ['HTTP_X_USER' => 'ada'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'A message says something');
    }

    public function testTheConversationsOfOthersAreNotFound(): void
    {
        $id = $this->direct('ada', 'bob', 'Private');

        foreach ([
            ['GET', '/messages/'.$id],
            ['GET', '/messages/'.$id.'/messages'],
            ['POST', '/messages/'.$id.'/reply'],
            ['POST', '/messages/'.$id.'/mute'],
            ['POST', '/messages/'.$id.'/leave'],
            ['GET', '/messages/0192f0c1-5c5e-7a3b-9e1d-0123456789ab'],
            ['GET', '/messages/new/not%20valid'],
        ] as [$method, $url]) {
            $this->client->request($method, $url, ['_token' => 'csrf-token', 'body' => 'Let me in'], server: ['HTTP_X_USER' => 'eve'] + self::SAME_SITE);
            self::assertResponseStatusCodeSame(404, $method.' '.$url);
        }

        $this->client->request('GET', '/messages/');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAFormWithoutTheTokenOrFromAnotherSiteIsRefused(): void
    {
        $id = $this->direct('ada', 'bob', 'Hi');

        $this->client->request('POST', '/messages/'.$id.'/reply', ['body' => 'No token'], server: ['HTTP_X_USER' => 'ada'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/messages/'.$id.'/reply', ['_token' => 'csrf-token', 'body' => 'Cross'], server: ['HTTP_X_USER' => 'ada', 'HTTP_ORIGIN' => 'https://evil.test', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(400);
        self::assertCount(1, $this->conversations()->thread(ConversationId::fromString($id), ParticipantId::fromString('ada')));
    }

    public function testAMemberMutesBlocksAndLeaves(): void
    {
        $id = $this->direct('ada', 'bob', 'Hi');

        $this->client->request('POST', '/messages/'.$id.'/mute', ['_token' => 'csrf-token', 'muted' => '1'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseRedirects('/messages/'.$id, 303);
        $this->client->request('GET', '/messages/'.$id, server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('.phil-conversation__actions', 'Unmute');
        $this->client->request('GET', '/messages/', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('.phil-conversation__entry', 'Muted');

        $this->client->request('POST', '/messages/settings', ['_token' => 'csrf-token', 'action' => 'block', 'participant' => 'ada'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseRedirects('/messages/settings', 303);
        $this->client->request('GET', '/messages/settings', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('main ul', 'ada');
        self::assertSelectorExists('input[name="who_can_write"][value="everyone"][checked]');

        $this->client->request('POST', '/messages/settings', ['_token' => 'csrf-token', 'action' => 'block', 'participant' => 'bob'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'A participant does not block themselves.');
        $this->client->request('POST', '/messages/settings', ['_token' => 'csrf-token', 'action' => 'shout'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/messages/settings', ['_token' => 'csrf-token', 'action' => 'allow', 'who_can_write' => 'aliens'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/messages/'.$id.'/reply', ['_token' => 'csrf-token', 'body' => 'Still there?'], server: ['HTTP_X_USER' => 'ada'] + self::SAME_SITE);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'This participant does not receive messages from you.');

        $this->client->request('POST', '/messages/settings', ['_token' => 'csrf-token', 'action' => 'unblock', 'participant' => 'ada'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        $this->client->request('GET', '/messages/settings', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('main', 'Nobody is blocked.');

        $this->client->request('POST', '/messages/'.$id.'/leave', ['_token' => 'csrf-token'], server: ['HTTP_X_USER' => 'bob'] + self::SAME_SITE);
        self::assertResponseRedirects('/messages/', 303);
        $this->client->request('GET', '/messages/', server: ['HTTP_X_USER' => 'bob']);
        self::assertSelectorTextContains('.phil-conversation__empty', 'No conversation yet.');
    }

    public function testALongConversationAndAFullInboxAreReadByPages(): void
    {
        $id = $this->direct('ada', 'bob', 'm1');

        foreach (range(2, 5) as $index) {
            $this->conversations()->reply(ConversationId::fromString($id), ParticipantId::fromString('ada'), 'm'.$index);
        }

        $crawler = $this->client->request('GET', '/messages/'.$id, server: ['HTTP_X_USER' => 'bob']);
        self::assertSame(['m3', 'm4', 'm5'], $crawler->filter('.phil-conversation__body')->each(static fn (Crawler $node): string => trim($node->text())));
        $older = $crawler->selectLink('Older messages')->link();
        $crawler = $this->client->click($older, ['HTTP_X_USER' => 'bob']);
        self::assertSame(['m1', 'm2'], $crawler->filter('.phil-conversation__body')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertSelectorNotExists('a:contains("Older messages")');

        foreach (['cy', 'dee', 'eve'] as $other) {
            $this->direct($other, 'bob', 'Hi');
        }

        $crawler = $this->client->request('GET', '/messages/', server: ['HTTP_X_USER' => 'bob']);
        self::assertCount(3, $crawler->filter('.phil-conversation__entry'));
        $crawler = $this->client->click($crawler->selectLink('Older conversations')->link(), ['HTTP_X_USER' => 'bob']);
        self::assertCount(1, $crawler->filter('.phil-conversation__entry'));
    }

    public function testTheRotationCommandIsWired(): void
    {
        self::assertInstanceOf(KeyRotation::class, self::getContainer()->get(KeyRotation::class));
        self::assertSame(['subjects' => 0, 'bodies' => 0], self::getContainer()->get(KeyRotation::class)->pending());
    }

    private function direct(string $from, string $to, string $body): string
    {
        return $this->conversations()->startDirect(ParticipantId::fromString($from), ParticipantId::fromString($to), $body)->id->value;
    }

    private function conversations(): Conversations
    {
        $conversations = self::getContainer()->get(Conversations::class);
        $this->assertInstanceOf(Conversations::class, $conversations);

        return $conversations;
    }
}
