<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\Model\ReceptionSettings;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Conversation::class)]
#[CoversClass(Message::class)]
#[CoversClass(Participation::class)]
#[CoversClass(ReceptionSettings::class)]
final class ModelsTest extends TestCase
{
    public function testAConversationKeepsItsLastMessageInUtc(): void
    {
        $id = ConversationId::generate();
        $conversation = new Conversation($id, Kind::Group, 'Board games', new DateTimeImmutable('2026-11-13 10:00:00-05:00'));

        self::assertSame('Board games', $conversation->subject());
        self::assertTrue($conversation->isGroup());
        self::assertFalse(new Conversation($id, Kind::Direct, null, new DateTimeImmutable())->isGroup());
        self::assertSame(0, $conversation->lastSequence());
        self::assertSame('2026-11-13T15:00:00+00:00', $conversation->lastMessageAt()->format(\DATE_ATOM));
        self::assertSame('2026-11-13T10:00:00-05:00', $conversation->startedAt->format(\DATE_ATOM), 'the start is kept as given');

        $conversation->received($this->message($id, '2026-11-13 12:00:00-05:00', 7));
        self::assertSame(7, $conversation->lastSequence());
        self::assertSame('2026-11-13T17:00:00+00:00', $conversation->lastMessageAt()->format(\DATE_ATOM));

        $conversation->received($this->message($id, '2026-11-13 12:01:00-05:00', 5));
        self::assertSame(7, $conversation->lastSequence(), 'a message numbered earlier never moves the last sequence back');

        $read = new Conversation($id, Kind::Group, null, new DateTimeImmutable('2026-11-01 00:00:00 UTC'), new DateTimeImmutable('2026-11-02 08:00:00+02:00'), 9);
        self::assertSame(['2026-11-02T06:00:00+00:00', 9], [$read->lastMessageAt()->format(\DATE_ATOM), $read->lastSequence()]);
    }

    public function testAMessageIsNumberedOnceKeptAndErasedWithoutAnAuthor(): void
    {
        $message = $this->message(ConversationId::generate(), '2026-11-13 10:00:00+01:00', 0);

        self::assertSame(0, $message->sequence());
        self::assertSame('2026-11-13T09:00:00+00:00', $message->sentAt->format(\DATE_ATOM));
        $message->numbered(12);
        self::assertSame(12, $message->sequence());
        self::assertFalse($message->isErased());
        self::assertTrue($message->isBy(ParticipantId::fromString('ada')));
        self::assertFalse($message->isBy(ParticipantId::fromString('bob')));

        $erased = new Message(MessageId::generate(), $message->conversation, null, null, new DateTimeImmutable());
        self::assertTrue($erased->isErased());
        self::assertSame(0, $erased->sequence(), 'not numbered until the store keeps it');
        self::assertFalse($erased->isBy(ParticipantId::fromString('ada')));
    }

    public function testAParticipationReadsForwardMutesAndLeaves(): void
    {
        $participation = new Participation(ConversationId::generate(), ParticipantId::fromString('ada'), new DateTimeImmutable('2026-11-13 10:00:00-05:00'));

        self::assertSame('2026-11-13T15:00:00+00:00', $participation->joinedAt->format(\DATE_ATOM));
        self::assertSame([0, false, true, null], [$participation->readUpTo(), $participation->isMuted(), $participation->isActive(), $participation->leftAt()]);

        $participation->read(4);
        $participation->read(2);
        self::assertSame(4, $participation->readUpTo());

        $participation->mute(true);
        self::assertTrue($participation->isMuted());
        $participation->mute(false);
        self::assertFalse($participation->isMuted());

        $participation->leave(new DateTimeImmutable('2026-11-13 11:00:00-05:00'));
        $participation->leave(new DateTimeImmutable('2026-11-14 11:00:00-05:00'));
        self::assertFalse($participation->isActive());
        self::assertSame('2026-11-13T16:00:00+00:00', $participation->leftAt()?->format(\DATE_ATOM), 'leaving again keeps the first departure');

        $participation->rejoin();
        self::assertTrue($participation->isActive());

        $read = new Participation(ConversationId::generate(), ParticipantId::fromString('bob'), new DateTimeImmutable(), 3, true, new DateTimeImmutable('2026-11-13 12:00:00+01:00'));
        self::assertSame([3, true, '2026-11-13T11:00:00+00:00'], [$read->readUpTo(), $read->isMuted(), $read->leftAt()?->format(\DATE_ATOM)]);
    }

    public function testReceptionSettingsAllowAndBlock(): void
    {
        $ada = ParticipantId::fromString('ada');
        $bob = ParticipantId::fromString('bob');
        $settings = new ReceptionSettings($ada);

        self::assertSame(WhoCanWrite::Everyone, $settings->whoCanWrite());
        self::assertSame([], $settings->blocked());
        $settings->allow(WhoCanWrite::Contacts);
        self::assertSame(WhoCanWrite::Contacts, $settings->whoCanWrite());

        $settings->block($bob);
        $settings->block($bob);
        self::assertTrue($settings->hasBlocked($bob));
        self::assertEquals([$bob], $settings->blocked());
        $settings->unblock($bob);
        self::assertFalse($settings->hasBlocked($bob));

        $given = new ReceptionSettings($ada, WhoCanWrite::Nobody, [$bob, ParticipantId::fromString('cy')]);
        self::assertSame(['bob', 'cy'], array_map(static fn (ParticipantId $other): string => $other->value, $given->blocked()));
    }

    public function testNobodyBlocksThemselves(): void
    {
        $this->expectException(InvalidParticipant::class);
        $this->expectExceptionMessageIs('A participant does not block themselves.');

        new ReceptionSettings(ParticipantId::fromString('ada'))->block(ParticipantId::fromString('ada'));
    }

    public function testTheBlocksAreBounded(): void
    {
        $settings = new ReceptionSettings(ParticipantId::fromString('ada'));

        for ($index = 0; $index < ReceptionSettings::MAX_BLOCKS; ++$index) {
            $settings->block(ParticipantId::fromString('member-'.$index));
        }

        $settings->block(ParticipantId::fromString('member-0'));
        self::assertCount(1000, $settings->blocked(), 'blocking again someone already blocked is not one more');

        $this->expectException(InvalidParticipant::class);
        $this->expectExceptionMessageIs('A participant blocks 1000 people at most.');

        $settings->block(ParticipantId::fromString('member-1000'));
    }

    private function message(ConversationId $id, string $at, int $sequence): Message
    {
        return new Message(MessageId::generate(), $id, ParticipantId::fromString('ada'), 'Hello', new DateTimeImmutable($at), $sequence);
    }
}
