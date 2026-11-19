<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Application;

use Phil\ConversationBundle\Application\ConversationLimits;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Application\Event\ConversationStarted;
use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Exception\DirectConversationExists;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\Exception\NotAParticipant;
use Phil\ConversationBundle\Domain\Exception\WritingRefused;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Tests\Unit\Fixture\ForgettingConnection;
use Phil\ConversationBundle\Tests\Unit\Fixture\RacingClock;
use Phil\ConversationBundle\Tests\Unit\Fixture\SavingRepository;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Conversations::class)]
#[CoversClass(ConversationStarted::class)]
#[CoversClass(MessageSent::class)]
#[CoversClass(NotAParticipant::class)]
#[CoversClass(WritingRefused::class)]
#[CoversClass(DirectConversationExists::class)]
#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(DbalReceptionRepository::class)]
#[CoversClass(ConversationTables::class)]
final class ConversationsTest extends TestCase
{
    private Store $store;

    private Conversations $conversations;

    protected function setUp(): void
    {
        $this->store = new Store();
        $this->conversations = $this->store->conversations;
    }

    public function testADirectConversationStartsAndIsTakenUpAgain(): void
    {
        $conversation = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), "  Hello Bob\n");

        self::assertSame(Kind::Direct, $conversation->kind);
        self::assertNull($conversation->subject());
        self::assertSame(['ada', 'bob'], $this->participants($conversation->id, true));
        self::assertSame(['Hello Bob'], $this->bodies($conversation->id, 'bob'));

        $started = $this->store->events->of(ConversationStarted::class);
        self::assertCount(1, $started);
        self::assertSame([$conversation->id->value, 'ada', ['bob']], [$started[0]->conversation, $started[0]->startedBy, $started[0]->participants]);

        $sent = $this->store->events->of(MessageSent::class)[0];
        self::assertSame([$conversation->id->value, 'ada', ['bob'], ['bob'], 1], [$sent->conversation, $sent->author, $sent->recipients, $sent->notified, $sent->sequence]);
        self::assertSame($this->messages($conversation->id, 'ada')[0]->id->value, $sent->message);

        $this->conversations->leave($conversation->id, Store::id('bob'));
        $again = $this->conversations->startDirect(Store::id('bob'), Store::id('ada'), 'Back');
        self::assertTrue($again->id->equals($conversation->id), 'the two share one direct conversation, whoever writes');
        self::assertSame(['ada', 'bob'], $this->participants($conversation->id, true), 'the one who left is back');
        self::assertSame(['Hello Bob', 'Back'], $this->bodies($conversation->id, 'ada'));
        self::assertCount(1, $this->store->events->of(ConversationStarted::class));
    }

    public function testAnExistingDirectConversationIsTakenUpWithoutStartingAnother(): void
    {
        $conversation = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hello');
        $repository = new SavingRepository($this->store->repository);
        $conversations = new Conversations($repository, $this->store->reception, $this->store->clock, $this->store->events);

        $again = $conversations->startDirect(Store::id('bob'), Store::id('ada'), 'Back');

        self::assertTrue($again->id->equals($conversation->id));
        self::assertSame([$conversation->id->value], array_values(array_unique($repository->saved)), 'no other conversation is even tried');
    }

    #[DataProvider('transactions')]
    public function testTwoFirstMessagesAtOnceGoInOneDirectConversation(bool $inTransaction): void
    {
        $first = null;
        $clock = new RacingClock(function () use (&$first): void {
            $first = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi Bob');
        });
        $late = new Conversations($this->store->repository, $this->store->reception, $clock, $this->store->events);

        if ($inTransaction) {
            $this->store->connection->beginTransaction();
        }

        $conversation = $late->startDirect(Store::id('bob'), Store::id('ada'), 'Hi Ada');

        if ($inTransaction) {
            self::assertTrue($this->store->connection->isTransactionActive(), "the refused key leaves the caller's transaction open");
            $this->store->connection->commit();
        }

        self::assertInstanceOf(Conversation::class, $first);
        self::assertTrue($conversation->id->equals($first->id), 'the later message goes in the conversation the other started');
        self::assertSame(1, $this->rows(ConversationTables::CONVERSATION), 'the refused conversation is removed');
        self::assertSame(2, $this->rows(ConversationTables::PARTICIPANT), 'with its participations');
        self::assertSame(['ada', 'bob'], $this->participants($first->id, true));
        self::assertSame(['Hi Bob', 'Hi Ada'], $this->bodies($first->id, 'ada'));
        self::assertCount(1, $this->store->events->of(ConversationStarted::class));
        self::assertSame(['ada', 'bob'], array_map(static fn (MessageSent $sent): string => $sent->author, $this->store->events->of(MessageSent::class)));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function transactions(): iterable
    {
        yield 'on its own' => [false];
        yield "in the caller's transaction" => [true];
    }

    public function testTheRefusalIsGivenWhenTheOtherConversationIsGoneToo(): void
    {
        $store = new Store(ForgettingConnection::sqlite());
        $clock = new RacingClock(static function () use ($store): void {
            $store->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi Bob');
        });
        $late = new Conversations($store->repository, $store->reception, $clock, $store->events);

        try {
            $late->startDirect(Store::id('bob'), Store::id('ada'), 'Hi Ada');
            self::fail('A message went in a conversation that no longer joins the two.');
        } catch (DirectConversationExists $directConversationExists) {
            self::assertSame('These two participants already share a direct conversation.', $directConversationExists->getMessage());
        }

        self::assertEquals(1, $store->connection->fetchOne('SELECT COUNT(*) FROM '.ConversationTables::CONVERSATION), 'the refused conversation is removed all the same');
    }

    public function testNobodyWritesToThemselvesNorAnEmptyMessage(): void
    {
        try {
            $this->conversations->startDirect(Store::id('ada'), Store::id('ada'), 'Hi');
            self::fail('A conversation with oneself started.');
        } catch (InvalidConversation $invalidConversation) {
            self::assertSame('A direct conversation is between two different participants.', $invalidConversation->getMessage());
        }

        $this->expectException(InvalidConversation::class);
        $this->expectExceptionMessageIs('A message says something: its body is empty.');

        $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), " \n\t");
    }

    public function testABodyLongerThanTheLimitIsRefused(): void
    {
        $conversations = new Store(limits: new ConversationLimits(5))->conversations;

        self::assertSame('éééé5', $this->bodies($conversations->startDirect(Store::id('ada'), Store::id('bob'), 'éééé5')->id, 'ada', $conversations)[0]);

        $this->expectException(InvalidConversation::class);
        $this->expectExceptionMessageIs('A message holds 5 characters at most.');

        $conversations->startDirect(Store::id('ada'), Store::id('bob'), 'éééé56');
    }

    public function testTheReceptionSettingsOfTheRecipientDecide(): void
    {
        $this->store->reception->allow(Store::id('bob'), WhoCanWrite::Nobody);

        try {
            $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
            self::fail('A message reached someone who receives none.');
        } catch (WritingRefused $writingRefused) {
            self::assertSame('This participant does not receive messages from you.', $writingRefused->getMessage());
        }

        try {
            $this->conversations->startGroup(Store::id('ada'), [Store::id('cy'), Store::id('bob')], null, 'Hi');
            self::fail('A group brought in someone who receives no messages.');
        } catch (WritingRefused) {
        }

        self::assertSame(0, $this->store->repository->unreadConversations(Store::id('ada')) + \count($this->store->conversations->inbox(Store::id('ada'))), 'nothing is kept of a refused conversation');
        self::assertSame([], $this->store->events->events);
    }

    public function testAGroupStartsWithItsSubjectAndItsBounds(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob'), Store::id('cy'), Store::id('bob'), Store::id('ada')], '  Board games  ', 'Who comes Friday?');

        self::assertSame(Kind::Group, $group->kind);
        self::assertSame('Board games', $group->subject());
        self::assertSame(['ada', 'bob', 'cy'], $this->participants($group->id, true));
        self::assertSame('Board games', $this->store->repository->find($group->id)?->subject());
        self::assertSame(['bob', 'cy'], $this->store->events->of(ConversationStarted::class)[0]->participants);
        self::assertNull($this->conversations->startGroup(Store::id('ada'), [Store::id('bob')], '   ', 'Hi')->subject());

        $small = new Store(limits: new ConversationLimits(groupSize: 3))->conversations;
        self::assertSame(['ada', 'bob', 'cy'], $this->participants($small->startGroup(Store::id('ada'), [Store::id('bob'), Store::id('cy')], null, 'Hi')->id, true, $small));

        try {
            $small->startGroup(Store::id('ada'), [Store::id('bob'), Store::id('cy'), Store::id('dee')], null, 'Hi');
            self::fail('A group larger than the limit started.');
        } catch (InvalidConversation $invalidConversation) {
            self::assertSame('A group conversation brings together 2 to 3 participants, the author included.', $invalidConversation->getMessage());
        }

        $this->expectException(InvalidConversation::class);

        $this->conversations->startGroup(Store::id('ada'), [Store::id('ada')], null, 'Hi');
    }

    public function testASubjectLongerThanTheLimitIsRefused(): void
    {
        $conversations = new Store(limits: new ConversationLimits(subjectLength: 3))->conversations;

        self::assertSame('Éco', $conversations->startOpen(Store::id('ada'), 'Éco', 'Hi')->subject());

        $this->expectException(InvalidConversation::class);
        $this->expectExceptionMessageIs('The subject of a conversation holds 3 characters at most.');

        $conversations->startOpen(Store::id('ada'), 'Écol', 'Hi');
    }

    public function testAnOpenConversationTakesOthersThroughJoin(): void
    {
        $open = $this->conversations->startOpen(Store::id('visitor-1'), 'My order', 'Where is it?');

        self::assertSame(Kind::Group, $open->kind);
        self::assertSame(['visitor-1'], $this->participants($open->id, true));
        self::assertSame([], $this->store->events->of(MessageSent::class)[0]->recipients);
        $this->store->clock->modify('+1 minute');

        $this->conversations->join($open->id, Store::id('agent-1'));
        $this->conversations->join($open->id, Store::id('agent-1'));
        self::assertSame(['visitor-1', 'agent-1'], $this->participants($open->id, true));
        self::assertSame(['Where is it?'], $this->bodies($open->id, 'agent-1'));

        $this->conversations->reply($open->id, Store::id('agent-1'), 'On its way.');
        self::assertSame(['visitor-1'], $this->store->events->of(MessageSent::class)[1]->recipients);

        $this->conversations->leave($open->id, Store::id('agent-1'));
        $this->conversations->join($open->id, Store::id('agent-1'));
        self::assertSame(['visitor-1', 'agent-1'], $this->participants($open->id, true), 'a participant who left joins again');
    }

    public function testJoiningByAParticipantFollowsTheNewcomersSettings(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'Hi');
        $this->store->reception->allow(Store::id('dee'), WhoCanWrite::Nobody);

        $this->conversations->join($group->id, Store::id('cy'), Store::id('bob'));
        self::assertSame(['ada', 'bob', 'cy'], $this->participants($group->id, true));

        try {
            $this->conversations->join($group->id, Store::id('dee'), Store::id('ada'));
            self::fail('Someone who receives no messages was brought in.');
        } catch (WritingRefused) {
        }

        try {
            $this->conversations->join($group->id, Store::id('eve'), Store::id('zed'));
            self::fail('Someone outside the group brought someone in.');
        } catch (NotAParticipant) {
        }

        $this->conversations->join($group->id, Store::id('dee'));
        self::assertSame(['ada', 'bob', 'cy', 'dee'], $this->participants($group->id, true), 'the application brings in whom it wants');
    }

    public function testJoiningIsForAnExistingGroupWithRoom(): void
    {
        $direct = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');

        try {
            $this->conversations->join($direct->id, Store::id('cy'));
            self::fail('A direct conversation took a third participant.');
        } catch (InvalidConversation $invalidConversation) {
            self::assertSame('Only a group conversation takes new participants.', $invalidConversation->getMessage());
        }

        try {
            $this->conversations->join(ConversationId::generate(), Store::id('cy'));
            self::fail('A participant joined a conversation that does not exist.');
        } catch (NotAParticipant) {
        }

        $conversations = new Store(limits: new ConversationLimits(groupSize: 2))->conversations;
        $group = $conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'Hi');
        $conversations->leave($group->id, Store::id('bob'));
        $conversations->join($group->id, Store::id('cy'));
        $conversations->join($group->id, Store::id('cy'));

        try {
            $conversations->join($group->id, Store::id('bob'));
            self::fail('A full group took one more.');
        } catch (InvalidConversation $invalidConversation) {
            self::assertSame('A group conversation brings together 2 to 2 participants, the author included.', $invalidConversation->getMessage());
        }
    }

    public function testOnlyActiveParticipantsReadAndWrite(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'Hi');

        foreach ([
            fn (): Conversation => $this->conversations->open($group->id, Store::id('cy')),
            fn (): array => $this->conversations->thread($group->id, Store::id('cy')),
            fn (): Message => $this->conversations->reply($group->id, Store::id('cy'), 'Let me in'),
            fn () => $this->conversations->markRead($group->id, Store::id('cy')),
            fn () => $this->conversations->mute($group->id, Store::id('cy')),
            fn () => $this->conversations->leave($group->id, Store::id('cy')),
            fn (): Conversation => $this->conversations->open(ConversationId::generate(), Store::id('ada')),
        ] as $index => $call) {
            try {
                $call();
                self::fail('Call '.$index.' read or wrote for someone outside the conversation.');
            } catch (NotAParticipant $notAParticipant) {
                self::assertStringStartsWith('No conversation "', $notAParticipant->getMessage());
            }
        }

        $this->conversations->leave($group->id, Store::id('bob'));

        $this->expectException(NotAParticipant::class);
        $this->expectExceptionMessageIs('No conversation "'.$group->id->value.'" among those of this participant.');

        $this->conversations->reply($group->id, Store::id('bob'), 'Still here?');
    }

    public function testRepliesFollowTheBlocksInADirectConversation(): void
    {
        $direct = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
        $this->conversations->leave($direct->id, Store::id('bob'));
        $this->conversations->reply($direct->id, Store::id('ada'), 'Are you there?');
        self::assertSame(['ada', 'bob'], $this->participants($direct->id, true), 'a reply brings the other back');

        $this->store->reception->block(Store::id('bob'), Store::id('ada'));

        foreach ([['ada', 'bob'], ['bob', 'ada']] as [$author, $other]) {
            try {
                $this->conversations->reply($direct->id, Store::id($author), 'Hello?');
                self::fail($author.' wrote to '.$other.' through a block.');
            } catch (WritingRefused) {
            }
        }

        self::assertCount(2, $this->messages($direct->id, 'ada'));
    }

    public function testInAGroupABlockOnlySilencesTheNotification(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob'), Store::id('cy'), Store::id('dee')], 'Club', 'Hi');
        $this->store->reception->block(Store::id('bob'), Store::id('ada'));
        $this->conversations->mute($group->id, Store::id('cy'));
        $this->conversations->leave($group->id, Store::id('dee'));

        $message = $this->conversations->reply($group->id, Store::id('ada'), 'Agenda');
        $sent = $this->store->events->of(MessageSent::class)[1];

        self::assertSame(['bob', 'cy'], $sent->recipients);
        self::assertSame([], $sent->notified);
        self::assertSame([$message->id->value, 2], [$sent->message, $sent->sequence]);

        $this->conversations->mute($group->id, Store::id('cy'), false);
        $this->conversations->reply($group->id, Store::id('ada'), 'Again');
        self::assertSame(['cy'], $this->store->events->of(MessageSent::class)[2]->notified);
    }

    public function testTheInboxCountsWhatIsUnread(): void
    {
        $first = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'One');
        $this->store->clock->modify('+1 minute');
        $second = $this->conversations->startGroup(Store::id('cy'), [Store::id('bob')], 'Club', 'Two');
        $this->conversations->reply($second->id, Store::id('cy'), 'Three');
        $this->store->clock->modify('+1 minute');
        $this->conversations->reply($first->id, Store::id('bob'), 'Four');

        self::assertSame(1, $this->conversations->unreadConversations(Store::id('bob')));
        $inbox = $this->conversations->inbox(Store::id('bob'));
        self::assertSame([$first->id->value, $second->id->value], array_map(static fn (InboxEntry $entry): string => $entry->conversation->id->value, $inbox));
        self::assertSame([0, 2], array_map(static fn (InboxEntry $entry): int => $entry->unread, $inbox), 'the own message reads the conversation up to it');
        self::assertSame(1, $this->conversations->unreadConversations(Store::id('ada')));
        self::assertSame([1], array_map(static fn (InboxEntry $entry): int => $entry->unread, $this->conversations->inbox(Store::id('ada'))));

        $this->conversations->markRead($second->id, Store::id('bob'));
        self::assertSame(0, $this->conversations->unreadConversations(Store::id('bob')));
        self::assertSame([0, 0], array_map(static fn (InboxEntry $entry): int => $entry->unread, $this->conversations->inbox(Store::id('bob'))));

        $page = $this->conversations->inbox(Store::id('bob'), 1);
        self::assertCount(1, $page);
        $older = $this->conversations->inbox(Store::id('bob'), 1, $page[0]->conversation->lastSequence());
        self::assertSame($second->id->value, $older[0]->conversation->id->value);
        self::assertSame([], $this->conversations->inbox(Store::id('bob'), 5, $older[0]->conversation->lastSequence()));

        $this->conversations->leave($second->id, Store::id('bob'));
        self::assertCount(1, $this->conversations->inbox(Store::id('bob')));
    }

    public function testAThreadIsReadByPages(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'm1');

        foreach (range(2, 7) as $index) {
            $this->conversations->reply($group->id, Store::id(1 === $index % 2 ? 'ada' : 'bob'), 'm'.$index);
        }

        self::assertSame(['m5', 'm6', 'm7'], $this->bodies($group->id, 'bob', null, 3));
        $latest = $this->conversations->thread($group->id, Store::id('bob'), 3);
        self::assertSame(['m2', 'm3', 'm4'], array_map(static fn (Message $message): ?string => $message->body, $this->conversations->thread($group->id, Store::id('bob'), 3, $latest[0]->sequence())));
        self::assertCount(7, $this->conversations->thread($group->id, Store::id('bob')));
        self::assertCount(7, $this->conversations->messages($group->id, 100));
        self::assertCount(2, $this->conversations->messages($group->id, 2));
        self::assertSame('m1', $this->conversations->messages($group->id, 2, $latest[0]->sequence() - 2)[0]->body);
        self::assertSame($group->id->value, $this->conversations->conversation($group->id)?->id->value);
        self::assertNull($this->conversations->conversation(ConversationId::generate()));
        self::assertCount(2, $this->conversations->participants($group->id));
    }

    public function testWritingToSomeoneWhoLeftBringsThemBack(): void
    {
        $direct = $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
        $this->conversations->leave($direct->id, Store::id('bob'));
        self::assertSame(['ada'], $this->participants($direct->id, true));

        $this->conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Are you there?');

        self::assertSame(['ada', 'bob'], $this->participants($direct->id, true), 'kept in the store, not only in memory');
    }

    public function testAnAuthorFirstInTheListStillLeavesTheOthersTheirMessage(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob'), Store::id('cy')], null, 'Hi');
        $this->conversations->mute($group->id, Store::id('cy'));

        $this->conversations->reply($group->id, Store::id('ada'), 'Hello all');

        $sent = $this->store->events->of(MessageSent::class)[1];
        self::assertSame(['bob', 'cy'], $sent->recipients);
        self::assertSame(['bob'], $sent->notified);
    }

    public function testSomeoneWhoLeftIsPassedOverNotTheEndOfTheList(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob'), Store::id('cy')], null, 'Hi');
        $this->conversations->leave($group->id, Store::id('bob'));

        $this->conversations->reply($group->id, Store::id('ada'), 'Hello cy');

        self::assertSame(['cy'], $this->store->events->of(MessageSent::class)[1]->recipients);
    }

    public function testARejoinKeepsWhatTheParticipantHadRead(): void
    {
        $conversations = new Store(limits: new ConversationLimits(groupSize: 3))->conversations;
        $group = $conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'Hi');
        $conversations->markRead($group->id, Store::id('bob'));
        $conversations->leave($group->id, Store::id('bob'));

        $before = $this->participation($conversations, $group->id, 'bob');

        $conversations->join($group->id, Store::id('bob'));

        $after = $this->participation($conversations, $group->id, 'bob');
        self::assertTrue($after->isActive());
        self::assertSame($before->joinedAt->format(\DATE_ATOM), $after->joinedAt->format(\DATE_ATOM));
        self::assertSame(1, $after->readUpTo(), 'joined again, not joined as a newcomer');
    }

    public function testSomeoneWhoLeftNeitherMutesNorLeavesAgain(): void
    {
        $group = $this->conversations->startGroup(Store::id('ada'), [Store::id('bob')], null, 'Hi');
        $this->conversations->leave($group->id, Store::id('bob'));

        foreach ([
            fn () => $this->conversations->mute($group->id, Store::id('bob')),
            fn () => $this->conversations->leave($group->id, Store::id('bob')),
        ] as $call) {
            try {
                $call();
                self::fail('Someone who left changed their participation.');
            } catch (NotAParticipant $notAParticipant) {
                self::assertStringStartsWith('No conversation "', $notAParticipant->getMessage());
            }
        }
    }

    public function testNothingNeedsADispatcher(): void
    {
        $store = new Store();
        $conversations = new Conversations($store->repository, $store->reception, $store->clock, null, new ConversationLimits());

        $direct = $conversations->startDirect(Store::id('ada'), Store::id('bob'), 'Hi');
        $conversations->reply($direct->id, Store::id('bob'), 'Hello');

        self::assertCount(2, $conversations->thread($direct->id, Store::id('ada')));
    }

    public function testAParticipationThatVanishesBetweenTwoReadsIsNotAParticipation(): void
    {
        $id = ConversationId::generate();
        $participation = new Participation($id, Store::id('ada'), $this->store->clock->now());
        $repository = self::createStub(ConversationRepository::class);
        $repository->method('find')->willReturn(new Conversation($id, Kind::Group, null, $this->store->clock->now()));
        $repository->method('participation')->willReturnOnConsecutiveCalls($participation, null);
        $conversations = new Conversations($repository, $this->store->reception, $this->store->clock, null, new ConversationLimits());

        $this->expectException(NotAParticipant::class);

        $conversations->mute($id, Store::id('ada'));
    }

    private function participation(Conversations $conversations, ConversationId $id, string $participant): Participation
    {
        $found = array_find($conversations->participants($id), static fn (Participation $participation): bool => $participation->participant->value === $participant);
        self::assertInstanceOf(Participation::class, $found);

        return $found;
    }

    /**
     * @return list<string>
     */
    private function participants(ConversationId $id, bool $active, ?Conversations $conversations = null): array
    {
        $participations = ($conversations ?? $this->conversations)->participants($id);

        if ($active) {
            $participations = array_filter($participations, static fn (Participation $participation): bool => $participation->isActive());
        }

        return array_values(array_map(static fn (Participation $participation): string => $participation->participant->value, $participations));
    }

    private function rows(string $table): int
    {
        $rows = $this->store->connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($rows);

        return (int) $rows;
    }

    /**
     * @return list<Message>
     */
    private function messages(ConversationId $id, string $reader): array
    {
        return $this->conversations->thread($id, Store::id($reader));
    }

    /**
     * @return list<?string>
     */
    private function bodies(ConversationId $id, string $reader, ?Conversations $conversations = null, ?int $limit = null): array
    {
        return array_map(static fn (Message $message): ?string => $message->body, ($conversations ?? $this->conversations)->thread($id, Store::id($reader), $limit));
    }
}
