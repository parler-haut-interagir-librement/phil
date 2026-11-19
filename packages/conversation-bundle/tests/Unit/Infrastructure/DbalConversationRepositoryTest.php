<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Exception\DirectConversationExists;
use Phil\ConversationBundle\Domain\Exception\UnreadableMessage;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ConversationBundle\Tests\Unit\Fixture\ChangedRowsConnection;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(ConversationTables::class)]
#[CoversClass(SchemaListener::class)]
#[CoversClass(DirectConversationExists::class)]
final class DbalConversationRepositoryTest extends TestCase
{
    public function testSubjectsAndBodiesAreSealedAtRestWithTheirKey(): void
    {
        $store = new Store();
        $group = $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Secret plans', 'The cake is in the fridge');

        $row = $store->connection->fetchAssociative('SELECT subject, subject_key FROM phil_conversation');
        self::assertIsArray($row);
        self::assertIsString($row['subject']);
        self::assertStringStartsWith('pcv1.a256gcm.', $row['subject']);
        self::assertStringNotContainsString('Secret', $row['subject']);
        self::assertSame('k2026', $row['subject_key']);

        $message = $store->connection->fetchAssociative('SELECT body, body_key, author FROM phil_conversation_message');
        self::assertIsArray($message);
        self::assertIsString($message['body']);
        self::assertStringNotContainsString('cake', $message['body']);
        self::assertSame(['k2026', 'ada'], [$message['body_key'], $message['author']]);

        self::assertSame('Secret plans', $store->repository->find($group->id)?->subject());
        self::assertNull($store->repository->find(ConversationId::generate()));

        // A body moved to another row does not open there: each is bound to its own.
        $other = $store->conversations->reply($group->id, Store::id('bob'), 'Thanks');
        $store->connection->executeStatement('UPDATE phil_conversation_message SET body = ? WHERE id = ?', [$message['body'], $other->id->value]);

        $this->expectException(UnreadableMessage::class);

        $store->repository->messages($group->id, 10);
    }

    public function testTheMomentsAreWrittenAndReadInUtcWhateverTheTimeZoneOfPhp(): void
    {
        $zone = date_default_timezone_get();
        $store = new Store();

        try {
            date_default_timezone_set('America/Toronto');
            $id = ConversationId::generate();
            $store->repository->save(new Conversation($id, Kind::Group, null, new DateTimeImmutable('2026-11-13T10:00:00-05:00')));
            $store->repository->saveParticipation(new Participation($id, Store::id('ada'), new DateTimeImmutable('2026-11-13T10:00:00-05:00'), 0, false, new DateTimeImmutable('2026-11-13T11:00:00-05:00')));
            $store->repository->add(new Message(MessageId::generate(), $id, Store::id('ada'), 'Hi', new DateTimeImmutable('2026-11-13T10:30:00-05:00')));
            date_default_timezone_set('Asia/Tokyo');
            $conversation = $store->repository->find($id);
            $participation = $store->repository->participation($id, Store::id('ada'));
            $message = $store->repository->messages($id, 1)[0];
        } finally {
            date_default_timezone_set($zone);
        }

        self::assertSame('2026-11-13 15:00:00', $store->connection->fetchOne('SELECT started_at FROM phil_conversation'));
        self::assertSame('2026-11-13 15:30:00', $store->connection->fetchOne('SELECT sent_at FROM phil_conversation_message'));
        self::assertSame('2026-11-13 16:00:00', $store->connection->fetchOne('SELECT left_at FROM phil_conversation_participant'));
        self::assertSame('2026-11-13T15:00:00+00:00', $conversation?->startedAt->format(\DATE_ATOM));
        self::assertSame('UTC', $conversation->lastMessageAt()->getTimezone()->getName());
        self::assertSame('2026-11-13T15:00:00+00:00', $participation?->joinedAt->format(\DATE_ATOM));
        self::assertSame('2026-11-13T16:00:00+00:00', $participation->leftAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-13T15:30:00+00:00', $message->sentAt->format(\DATE_ATOM));
        self::assertSame(1, $message->sequence());
    }

    public function testTheLastSequenceNeverMovesBack(): void
    {
        $store = new Store();
        $id = ConversationId::generate();
        $conversation = new Conversation($id, Kind::Group, null, new DateTimeImmutable('2026-11-13 10:00:00 UTC'));
        $store->repository->save($conversation);

        $late = new Conversation($id, Kind::Group, 'Renamed', new DateTimeImmutable('2026-11-13 10:00:00 UTC'), new DateTimeImmutable('2026-11-13 10:05:00 UTC'), 9);
        $early = new Conversation($id, Kind::Group, 'Renamed', new DateTimeImmutable('2026-11-13 10:00:00 UTC'), new DateTimeImmutable('2026-11-13 10:04:00 UTC'), 8);
        $store->repository->save($late);
        $store->repository->save($early);

        $read = $store->repository->find($id);
        self::assertSame([9, '2026-11-13T10:05:00+00:00', 'Renamed'], [$read?->lastSequence(), $read?->lastMessageAt()->format(\DATE_ATOM), $read?->subject()]);

        $store->repository->save(new Conversation($id, Kind::Group, null, new DateTimeImmutable('2026-11-13 10:00:00 UTC'), null, 9));
        self::assertNull($store->repository->find($id)?->subject());
        self::assertNull($store->connection->fetchOne('SELECT subject_key FROM phil_conversation'));
    }

    public function testTwoParticipantsShareOneDirectConversation(): void
    {
        $store = new Store();
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');

        self::assertSame(DbalConversationRepository::directKey(Store::id('bob'), Store::id('ada')), DbalConversationRepository::directKey(Store::id('ada'), Store::id('bob')));
        self::assertSame(hash('sha256', "ada\0bob"), DbalConversationRepository::directKey(Store::id('bob'), Store::id('ada')));
        self::assertSame($direct->id->value, $store->repository->directBetween(Store::id('bob'), Store::id('ada'))?->id->value);
        self::assertNull($store->repository->directBetween(Store::id('ada'), Store::id('cy')));

        $group = $store->conversations->startGroup(Store::id('ada'), [Store::id('cy')], null, 'Hi');
        self::assertNull($store->repository->directBetween(Store::id('ada'), Store::id('cy')), 'a group of two is not a direct conversation');
        self::assertNull($store->connection->fetchOne('SELECT direct_key FROM phil_conversation WHERE id = ?', [$group->id->value]));

        $copy = ConversationId::generate();
        $store->repository->save(new Conversation($copy, Kind::Direct, null, new DateTimeImmutable()));
        $store->repository->saveParticipation(new Participation($copy, Store::id('ada'), new DateTimeImmutable()));

        try {
            $store->repository->saveParticipation(new Participation($copy, Store::id('bob'), new DateTimeImmutable()));
            self::fail('A second direct conversation joined the same two participants.');
        } catch (DirectConversationExists) {
            self::assertNull($store->repository->find($copy), 'the second one is removed');
            self::assertSame([], $store->repository->participations($copy));
            self::assertSame($direct->id->value, $store->repository->directBetween(Store::id('ada'), Store::id('bob'))?->id->value);
        }
    }

    public function testAParticipationIsInsertedOnceThenUpdated(): void
    {
        $store = new Store();
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
        $participation = $store->repository->participation($direct->id, Store::id('bob'));
        self::assertInstanceOf(Participation::class, $participation);

        $store->repository->saveParticipation($participation);
        $participation->mute(true);
        $participation->read(1);

        $store->repository->saveParticipation($participation);

        $read = $store->repository->participation($direct->id, Store::id('bob'));
        self::assertSame([true, 1], [$read?->isMuted(), $read?->readUpTo()]);
        self::assertCount(2, $store->repository->participations($direct->id));
        self::assertNull($store->repository->participation($direct->id, Store::id('cy')));
    }

    public function testAParticipantIsForgotten(): void
    {
        $store = new Store();
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'From ada');
        $store->conversations->reply($direct->id, Store::id('bob'), 'From bob');
        $alone = $store->conversations->startOpen(Store::id('ada'), 'Alone', 'Only me');
        $group = $store->conversations->startGroup(Store::id('cy'), [Store::id('bob')], null, 'Unrelated');

        self::assertCount(2, $store->repository->conversationsOf(Store::id('ada')));
        self::assertSame(['From ada', 'Only me'], array_map(static fn (Message $message): ?string => $message->body, $store->repository->messagesBy(Store::id('ada'))));

        self::assertSame(['messages' => 2, 'participations' => 2, 'conversations' => 1], $store->repository->forget(Store::id('ada')));

        self::assertNull($store->repository->find($alone->id));
        self::assertSame([], $store->repository->messages($alone->id, 10));
        $messages = $store->repository->messages($direct->id, 10);
        self::assertSame([null, 'From bob'], array_map(static fn (Message $message): ?string => $message->body, $messages));
        self::assertTrue($messages[0]->isErased());
        self::assertNull($store->connection->fetchOne('SELECT body_key FROM phil_conversation_message WHERE seq = ?', [$messages[0]->sequence()]));
        self::assertNull($store->connection->fetchOne('SELECT direct_key FROM phil_conversation WHERE id = ?', [$direct->id->value]), 'the hash of the pair goes with the person');
        self::assertSame([], $store->repository->conversationsOf(Store::id('ada')));
        self::assertCount(2, $store->repository->messages($group->id, 10) + $store->repository->messages($direct->id, 10));
        self::assertSame(['messages' => 0, 'participations' => 0, 'conversations' => 0], $store->repository->forget(Store::id('ada')));
    }

    public function testAParticipationTheStoreCountsNoChangeOfIsNotInsertedTwice(): void
    {
        $store = new Store(ChangedRowsConnection::sqlite());
        $id = ConversationId::generate();
        $participation = new Participation($id, Store::id('ada'), new DateTimeImmutable('2026-11-13 10:00:00 UTC'));

        $store->repository->saveParticipation($participation);
        $store->repository->saveParticipation($participation);

        $participation->mute(true);
        $store->repository->saveParticipation($participation);

        self::assertCount(1, $store->repository->participations($id));
        self::assertTrue($store->repository->participation($id, Store::id('ada'))?->isMuted());
    }

    public function testAMuteIsKeptAsABooleanNotAsText(): void
    {
        $store = new Store();
        $group = $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'Hi');

        self::assertSame(0, $store->connection->fetchOne('SELECT muted FROM phil_conversation_participant WHERE participant = ?', ['bob']));
        $store->conversations->mute($group->id, Store::id('bob'));
        self::assertSame(1, $store->connection->fetchOne('SELECT muted FROM phil_conversation_participant WHERE participant = ?', ['bob']));
        $store->conversations->mute($group->id, Store::id('bob'), false);
        self::assertSame(0, $store->connection->fetchOne('SELECT muted FROM phil_conversation_participant WHERE participant = ?', ['bob']));
    }

    public function testSavingAConversationLeavesTheOthersAlone(): void
    {
        $store = new Store();
        $first = $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'First', 'Hi');
        $second = $store->conversations->startGroup(Store::id('ada'), [Store::id('cy')], 'Second', 'Hello');

        $store->conversations->reply($first->id, Store::id('bob'), 'Again');

        self::assertSame('First', $store->repository->find($first->id)?->subject());
        self::assertSame('Second', $store->repository->find($second->id)?->subject());
    }

    public function testADirectConversationGetsItsKeyWhateverTheOrderTheParticipantsCame(): void
    {
        $store = new Store();
        $first = $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
        $second = $store->conversations->startDirect(Store::id('cy'), Store::id('dee'), 'Hello');

        self::assertSame(DbalConversationRepository::directKey(Store::id('ada'), Store::id('bob')), $store->connection->fetchOne('SELECT direct_key FROM phil_conversation WHERE id = ?', [$first->id->value]));
        self::assertSame(DbalConversationRepository::directKey(Store::id('cy'), Store::id('dee')), $store->connection->fetchOne('SELECT direct_key FROM phil_conversation WHERE id = ?', [$second->id->value]));
    }

    public function testAnErasedMessageHasNoAuthor(): void
    {
        $store = new Store();
        $id = ConversationId::generate();
        $store->repository->save(new Conversation($id, Kind::Group, null, new DateTimeImmutable('2026-11-13 10:00:00 UTC')));

        $store->repository->add(new Message(MessageId::generate(), $id, null, null, new DateTimeImmutable('2026-11-13 10:00:00 UTC')));

        self::assertTrue($store->repository->messages($id, 5)[0]->isErased());
        self::assertNull($store->connection->fetchOne('SELECT author FROM phil_conversation_message'));
    }

    public function testTheInboxHasTheLatestConversationFirstWhateverTheOrderTheyStarted(): void
    {
        $store = new Store();
        $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'First', 'Hi');

        $second = $store->conversations->startGroup(Store::id('cy'), [Store::id('bob')], 'Second', 'Hello');
        $store->conversations->startGroup(Store::id('dee'), [Store::id('bob')], 'Third', 'Hey');
        $store->conversations->reply($second->id, Store::id('cy'), 'Again');

        $order = static fn (array $entries): array => array_map(static fn (InboxEntry $entry): string => (string) $entry->conversation->subject(), $entries);

        self::assertSame(['Second', 'Third', 'First'], $order($store->repository->inbox(Store::id('bob'), 10)));
        self::assertSame(['Second', 'Third'], $order($store->repository->inbox(Store::id('bob'), 2)));
        self::assertSame([2, 1, 1], array_map(static fn (InboxEntry $entry): int => $entry->unread, $store->repository->inbox(Store::id('bob'), 10)));
    }

    public function testTheConversationsOfAParticipantCarryNoUnreadCount(): void
    {
        $store = new Store();
        $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'First', 'Hi');
        $store->conversations->startGroup(Store::id('cy'), [Store::id('bob')], 'Second', 'Hello');

        $entries = $store->repository->conversationsOf(Store::id('bob'));

        self::assertSame(['First', 'Second'], array_map(static fn (InboxEntry $entry): ?string => $entry->conversation->subject(), $entries));
        self::assertSame([0, 0], array_map(static fn (InboxEntry $entry): int => $entry->unread, $entries));
    }

    public function testASubjectAndABodyAreSealedInTheContextOfTheirRow(): void
    {
        $conversation = ConversationId::fromString('11111111-1111-4111-8111-111111111111');
        $message = MessageId::fromString('22222222-2222-4222-8222-222222222222');

        self::assertSame('phil_conversation.subject.11111111-1111-4111-8111-111111111111', DbalConversationRepository::subjectContext($conversation));
        self::assertSame('phil_conversation.message.22222222-2222-4222-8222-222222222222', DbalConversationRepository::messageContext($message));
    }

    public function testTheTablesAreAddedToTheSchemaOfTheirConnectionOnly(): void
    {
        $store = new Store();
        $schema = ConversationTables::configure(Schema::editor()->create());

        self::assertTrue($schema->hasTable(ConversationTables::CONVERSATION));
        self::assertTrue($schema->hasTable(ConversationTables::PARTICIPANT));
        self::assertTrue($schema->hasTable(ConversationTables::MESSAGE));
        self::assertTrue($schema->hasTable(ConversationTables::RECEPTION));
        self::assertTrue($schema->hasTable(ConversationTables::BLOCK));
        self::assertCount(5, ConversationTables::configure($schema)->getTables(), 'the tables already there are left as they are');

        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($store->connection);
        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new SchemaListener($store->connection)->postGenerateSchema($event);
        self::assertCount(5, $event->getSchema()->getTables());

        $other = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new SchemaListener(Store::sqlite())->postGenerateSchema($other);
        self::assertCount(0, $other->getSchema()->getTables());
    }
}
