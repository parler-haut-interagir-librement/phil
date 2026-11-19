<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\Exception\TicketNotFound;
use Phil\HelpdeskBundle\Domain\Exception\TransitionRefused;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Ticket::class)]
#[CoversClass(QueueName::class)]
#[CoversClass(Status::class)]
#[CoversClass(InvalidQueue::class)]
#[CoversClass(TicketNotFound::class)]
#[CoversClass(TransitionRefused::class)]
#[CoversClass(CannedReplyNotFound::class)]
final class DomainTest extends TestCase
{
    public function testAQueueIsNamedBySlug(): void
    {
        self::assertSame('billing-2', QueueName::fromString('billing-2')->value);
        self::assertSame('a_b', (string) QueueName::fromString('a_b'));
        self::assertSame(str_repeat('a', 64), QueueName::fromString(str_repeat('a', 64))->value);
    }

    #[DataProvider('malformed')]
    public function testAMalformedQueueIsRefused(string $name): void
    {
        $this->expectException(InvalidQueue::class);
        $this->expectExceptionMessageIs('The name of a queue holds 1 to 64 lowercase letters, digits, dashes and underscores.');

        QueueName::fromString($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Billing'];
        yield 'a space' => ['the billing'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a newline' => ["billing\n"];
    }

    public function testATicketKeepsItsStateAndMomentsInUtc(): void
    {
        $id = ConversationId::generate();
        $ada = ParticipantId::fromString('ada');
        $ticket = new Ticket($id, QueueName::fromString('billing'), $ada, new DateTimeImmutable('2026-11-13 10:00:00-05:00'));

        self::assertSame([Status::New, 'new', null, null], [$ticket->status(), $ticket->getMarking(), $ticket->assignee(), $ticket->closedAt()]);
        self::assertSame('2026-11-13T15:00:00+00:00', $ticket->openedAt->format(\DATE_ATOM));
        self::assertSame('2026-11-13T15:00:00+00:00', $ticket->updatedAt()->format(\DATE_ATOM));
        self::assertTrue($ticket->isRequestedBy($ada));
        self::assertFalse($ticket->isRequestedBy(ParticipantId::fromString('bob')));
        self::assertSame($ada, $ticket->requester());

        $ticket->assign(ParticipantId::fromString('agent'));
        self::assertSame('agent', $ticket->assignee()?->value);
        $ticket->move(QueueName::fromString('tech'));
        self::assertSame('tech', $ticket->queue()->value);

        $ticket->setMarking('closed');
        $ticket->touch(new DateTimeImmutable('2026-11-13 12:00:00-05:00'));
        self::assertSame(Status::Closed, $ticket->status());
        self::assertSame('2026-11-13T17:00:00+00:00', $ticket->closedAt()?->format(\DATE_ATOM));
        $ticket->touch(new DateTimeImmutable('2026-11-13 13:00:00-05:00'));
        self::assertSame('2026-11-13T17:00:00+00:00', $ticket->closedAt()?->format(\DATE_ATOM), 'a closed request keeps the moment it was closed');
        self::assertSame('2026-11-13T18:00:00+00:00', $ticket->updatedAt()->format(\DATE_ATOM));

        $ticket->setMarking('open');
        $ticket->touch(new DateTimeImmutable('2026-11-13 14:00:00-05:00'));
        self::assertNull($ticket->closedAt());

        $erased = new Ticket($id, QueueName::fromString('billing'), null, new DateTimeImmutable('2026-11-01 00:00:00 UTC'), Status::Resolved, null, new DateTimeImmutable('2026-11-02 01:00:00+01:00'), new DateTimeImmutable('2026-11-03 01:00:00+01:00'));
        self::assertFalse($erased->isRequestedBy($ada));
        self::assertSame(['2026-11-02T00:00:00+00:00', '2026-11-03T00:00:00+00:00'], [$erased->updatedAt()->format(\DATE_ATOM), $erased->closedAt()?->format(\DATE_ATOM)]);
    }

    public function testTheStatusesAndTheMessagesOfTheFailures(): void
    {
        self::assertTrue(Status::Closed->isClosed());
        self::assertFalse(Status::Resolved->isClosed());
        self::assertSame('No queue "x" is declared.', InvalidQueue::unknown('x')->getMessage());
        self::assertSame('No request "r" for you.', TicketNotFound::of('r')->getMessage());
        self::assertSame('No canned reply "c" for this request.', CannedReplyNotFound::of('c')->getMessage());
        $previous = new RuntimeException();
        $refused = TransitionRefused::of('reopen', 'closed', $previous);
        self::assertSame('A request that is closed cannot be changed by "reopen".', $refused->getMessage());
        self::assertSame($previous, $refused->getPrevious());
    }
}
