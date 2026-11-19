<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Application\NoCannedReplies;
use Phil\HelpdeskBundle\Application\Queue;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Application\TicketWorkflow;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;

#[CoversClass(Queues::class)]
#[CoversClass(Queue::class)]
#[CoversClass(CannedReply::class)]
#[CoversClass(NoCannedReplies::class)]
#[CoversClass(TicketWorkflow::class)]
final class QueuesTest extends TestCase
{
    public function testTheQueuesOfTheConfigurationInTheirOrder(): void
    {
        $queues = Queues::fromDeclarations([
            'tech' => ['label' => 'queue.tech', 'roles' => ['ROLE_TECH']],
            'billing' => ['label' => 'queue.billing', 'roles' => ['ROLE_BILLING', 'ROLE_ADMIN']],
        ]);

        self::assertSame(['tech', 'billing'], array_map(static fn (Queue $queue): string => $queue->name->value, $queues->all()));
        $billing = $queues->get(QueueName::fromString('billing'));
        self::assertSame(['queue.billing', ['ROLE_BILLING', 'ROLE_ADMIN']], [$billing->label, $billing->roles]);
        self::assertSame([], new Queues()->all());

        $this->expectException(InvalidQueue::class);
        $this->expectExceptionMessageIs('No queue "sales" is declared.');

        $queues->get(QueueName::fromString('sales'));
    }

    public function testWithoutCannedRepliesNoneIsOffered(): void
    {
        $ticket = new Ticket(ConversationId::generate(), QueueName::fromString('tech'), null, new DateTimeImmutable());
        $reply = new CannedReply('thanks', 'Thanks');

        self::assertSame(['thanks', 'Thanks'], [$reply->id, $reply->label]);
        self::assertSame([], new NoCannedReplies()->for($ticket));

        $this->expectException(CannedReplyNotFound::class);

        new NoCannedReplies()->render('thanks', $ticket);
    }

    public function testTheWorkflowOfARequest(): void
    {
        $definition = TicketWorkflow::definition();

        self::assertSame(['new', 'open', 'waiting', 'resolved', 'closed'], array_keys($definition->getPlaces()));
        self::assertSame(['new'], $definition->getInitialPlaces());
        $edges = array_map(static fn (Transition $transition): string => $transition->getName().':'.implode(',', array_filter($transition->getFroms(), is_string(...))).'>'.implode(',', array_filter($transition->getTos(), is_string(...))), $definition->getTransitions());
        self::assertSame([
            'take:new>open',
            'wait:new>waiting',
            'wait:open>waiting',
            'answer:waiting>open',
            'answer:resolved>open',
            'resolve:new>resolved',
            'resolve:open>resolved',
            'resolve:waiting>resolved',
            'reopen:resolved>open',
            'close:new>closed',
            'close:open>closed',
            'close:waiting>closed',
            'close:resolved>closed',
        ], $edges);
        self::assertSame(['take', 'resolve', 'reopen', 'close'], TicketWorkflow::MANUAL);
        self::assertSame('phil_helpdesk_ticket', TicketWorkflow::NAME);

        $ticket = new Ticket(ConversationId::generate(), QueueName::fromString('tech'), null, new DateTimeImmutable());
        self::assertSame(['new' => 1], TicketWorkflow::markingStore()->getMarking($ticket)->getPlaces());
    }
}
