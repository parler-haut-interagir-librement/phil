<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use Phil\ConversationBundle\Tests\Unit\Fixture\RacingClock;
use Phil\ConversationBundle\Tests\Unit\Fixture\SealerStandIn;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\TestCase;

/**
 * The bundle's tables and queries on a real server, read from the environment: the tables created from
 * `ConversationTables`, the conversations, inboxes and pages kept and read back, the moments in UTC, the unique direct
 * conversation, the erasure, and a rotation of the master key.
 */
abstract class ServerTestCase extends TestCase
{
    private Connection $connection;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));

        foreach ([ConversationTables::BLOCK, ConversationTables::RECEPTION, ConversationTables::MESSAGE, ConversationTables::PARTICIPANT, ConversationTables::CONVERSATION] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        Store::create($this->connection);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    public function testConversationsAreKeptReadByPagesAndCounted(): void
    {
        $store = new Store($this->connection);
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hello ✓');
        $store->clock->modify('+1 minute');
        $group = $store->conversations->startGroup(Store::id('cy'), [Store::id('bob'), Store::id('ada')], 'Club ✓', 'g1');

        foreach (range(2, 6) as $index) {
            $store->conversations->reply($group->id, Store::id('cy'), 'g'.$index);
        }

        $store->conversations->mute($group->id, Store::id('ada'));

        self::assertSame('Club ✓', $store->repository->find($group->id)?->subject());
        self::assertSame(['g4', 'g5', 'g6'], $this->bodies($store->conversations->thread($group->id, Store::id('bob'), 3)));
        $page = $store->conversations->thread($group->id, Store::id('bob'), 3);
        self::assertSame(['g1', 'g2', 'g3'], $this->bodies($store->conversations->thread($group->id, Store::id('bob'), 3, $page[0]->sequence())));

        $inbox = $store->conversations->inbox(Store::id('bob'));
        self::assertSame([$group->id->value, $direct->id->value], array_map(static fn (InboxEntry $entry): string => $entry->conversation->id->value, $inbox));
        self::assertSame([6, 1], array_map(static fn (InboxEntry $entry): int => $entry->unread, $inbox));
        self::assertSame(2, $store->conversations->unreadConversations(Store::id('bob')));
        self::assertTrue($store->repository->participation($group->id, Store::id('ada'))?->isMuted());
        self::assertFalse($store->repository->participation($group->id, Store::id('bob'))?->isMuted());

        $store->conversations->markRead($group->id, Store::id('bob'));
        self::assertSame(1, $store->conversations->unreadConversations(Store::id('bob')));
        self::assertSame('2026-11-13T10:01:00+00:00', $store->repository->find($group->id)?->lastMessageAt()->format(\DATE_ATOM));
        $sentAt = $this->connection->fetchOne('SELECT sent_at FROM '.ConversationTables::MESSAGE.' WHERE conversation_id = ?', [$direct->id->value]);
        self::assertIsString($sentAt);
        self::assertSame('2026-11-13 10:00:00', substr($sentAt, 0, 19));
    }

    public function testTwoParticipantsShareOneDirectConversation(): void
    {
        $store = new Store($this->connection);
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
        self::assertSame($direct->id->value, $store->conversations->startDirect(Store::id('bob'), Store::id('ada'), 'Hi back')->id->value);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert(ConversationTables::CONVERSATION, [
            'id' => '0192f0c1-5c5e-7a3b-9e1d-0123456789ab',
            'kind' => 'direct',
            'direct_key' => $this->connection->fetchOne('SELECT direct_key FROM '.ConversationTables::CONVERSATION),
            'started_at' => '2026-11-13 10:00:00',
            'last_message_at' => '2026-11-13 10:00:00',
            'last_sequence' => 0,
        ]);
    }

    public function testTwoFirstMessagesAtOnceGoInOneDirectConversation(): void
    {
        $store = new Store($this->connection);
        $first = null;
        $clock = new RacingClock(static function () use ($store, &$first): void {
            $first = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi Bob');
        });
        $late = new Conversations($store->repository, $store->reception, $clock, $store->events);

        // In the caller's transaction, which PostgreSQL would abort on the refused key without the savepoint.
        $this->connection->beginTransaction();
        $conversation = $late->startDirect(Store::id('bob'), Store::id('ada'), 'Hi Ada');
        $this->connection->commit();

        self::assertInstanceOf(Conversation::class, $first);
        self::assertSame($first->id->value, $conversation->id->value);
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM '.ConversationTables::CONVERSATION));
        self::assertEquals(2, $this->connection->fetchOne('SELECT COUNT(*) FROM '.ConversationTables::PARTICIPANT));
        self::assertSame(['Hi Bob', 'Hi Ada'], array_map(static fn (Message $message): ?string => $message->body, $store->conversations->thread($first->id, Store::id('ada'))));
    }

    public function testAParticipantIsErasedAndTheSettingsKept(): void
    {
        $store = new Store($this->connection);
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'From ada');
        $store->conversations->reply($direct->id, Store::id('bob'), 'From bob');
        $store->conversations->startOpen(Store::id('ada'), null, 'Alone');
        $store->reception->allow(Store::id('ada'), WhoCanWrite::Contacts);
        $store->reception->block(Store::id('bob'), Store::id('ada'));

        self::assertSame(WhoCanWrite::Contacts, $store->receptions->of(Store::id('ada'))->whoCanWrite());
        self::assertSame(['messages' => 2, 'participations' => 2, 'conversations' => 1], $store->repository->forget(Store::id('ada')));
        self::assertSame(2, $store->receptions->forget(Store::id('ada')));
        self::assertSame([null, 'From bob'], $this->bodies($store->repository->messages($direct->id, 5)));
        self::assertSame([$direct->id->value], $this->connection->fetchFirstColumn('SELECT id FROM '.ConversationTables::CONVERSATION));
    }

    public function testTheMasterKeyRotates(): void
    {
        $before = new Store($this->connection, new SealerStandIn('k2025'));
        $group = $before->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Plans', 'One');
        $before->conversations->reply($group->id, Store::id('bob'), 'Two');

        $after = new Store($this->connection);
        $rotation = new KeyRotation($this->connection, $after->sealer);
        self::assertSame(['subjects' => 1, 'bodies' => 2], $rotation->pending());
        self::assertSame(['subjects' => 1, 'bodies' => 2], $rotation->rotate());
        self::assertSame(['subjects' => 0, 'bodies' => 0], $rotation->pending());
        self::assertSame(['One', 'Two'], $this->bodies($after->repository->messages($group->id, 5)));
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<?string>
     */
    private function bodies(array $messages): array
    {
        return array_map(static fn (Message $message): ?string => $message->body, $messages);
    }
}
