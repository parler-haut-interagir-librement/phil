<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Application;

use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\HelpdeskBundle\Application\Event\TicketOpened;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\NoCannedReplies;
use Phil\HelpdeskBundle\Application\Queue;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\Exception\TicketNotFound;
use Phil\HelpdeskBundle\Domain\Exception\TransitionRefused;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\Desk;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\FixedCannedReplies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\GuardEvent;

#[CoversClass(Helpdesk::class)]
#[CoversClass(TicketOpened::class)]
#[CoversClass(TicketAssigned::class)]
#[CoversClass(DbalTicketRepository::class)]
#[CoversClass(HelpdeskTables::class)]
final class HelpdeskTest extends TestCase
{
    public function testARequestIsAConversationOpenedByItsRequesterAlone(): void
    {
        $desk = new Desk();
        $ticket = $desk->helpdesk->open(Desk::id('ada'), Desk::queue('billing'), 'Invoice', 'My invoice is wrong');

        self::assertSame([Status::New, 'billing', 'ada', null], [$ticket->status(), $ticket->queue()->value, $ticket->requester()?->value, $ticket->assignee()]);
        $conversation = $desk->conversations->conversation($ticket->conversation);
        self::assertSame('Invoice', $conversation?->subject());
        self::assertTrue($conversation->isGroup());
        self::assertSame(['ada'], $this->participants($desk, $ticket->conversation));
        self::assertSame(['My invoice is wrong'], $this->bodies($desk, $ticket->conversation));
        self::assertEquals($ticket, $desk->tickets->find($ticket->conversation));

        $opened = $desk->events->of(TicketOpened::class);
        self::assertCount(1, $opened);
        self::assertSame([$ticket->conversation->value, 'billing', 'ada'], [$opened[0]->conversation, $opened[0]->queue, $opened[0]->requester]);
    }

    public function testARequestIsOpenedInADeclaredQueueWithASubject(): void
    {
        $desk = new Desk();

        try {
            $desk->helpdesk->open(Desk::id('ada'), Desk::queue('sales'), 'Hi', 'Hello');
            self::fail('A request was opened in an undeclared queue.');
        } catch (InvalidQueue) {
        }

        self::assertSame([], $desk->events->events, 'nothing is started for an undeclared queue');

        $this->expectException(InvalidConversation::class);

        $desk->helpdesk->open(Desk::id('ada'), Desk::queue('billing'), 'Hi', ' ');
    }

    public function testTheRequesterSeesOnlyTheirRequests(): void
    {
        $desk = new Desk();
        $mine = $desk->open('ada');
        $desk->clock->modify('+1 minute');
        $later = $desk->open('ada', 'tech');
        $desk->open('bob');

        self::assertSame($mine->value, $desk->helpdesk->ofRequester($mine, Desk::id('ada'))->conversation->value);
        self::assertSame([$later->value, $mine->value], array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $desk->helpdesk->requests(Desk::id('ada'))));
        self::assertSame([], $desk->helpdesk->requests(Desk::id('ada'), 2));
        self::assertCount(2, $desk->helpdesk->requests(Desk::id('ada'), 0), 'a page before the first is the first');

        foreach ([[$mine, 'bob'], [ConversationId::generate(), 'ada']] as [$id, $who]) {
            try {
                $desk->helpdesk->ofRequester($id, Desk::id($who));
                self::fail('A request was shown to someone else.');
            } catch (TicketNotFound $ticketNotFound) {
                self::assertSame('No request "'.$id->value.'" for you.', $ticketNotFound->getMessage());
            }
        }
    }

    public function testAnAgentAnswersAndTheRequestWaitsForTheRequester(): void
    {
        $desk = new Desk();
        $id = $desk->open();
        $desk->clock->modify('+5 minutes');

        $message = $desk->helpdesk->reply($id, Desk::id('agent-1'), 'We will fix it.');

        $ticket = $desk->tickets->find($id);
        self::assertSame([Status::Waiting, 'agent-1'], [$ticket?->status(), $ticket?->assignee()?->value]);
        self::assertSame('2026-11-13T10:05:00+00:00', $ticket?->updatedAt()->format(\DATE_ATOM));
        self::assertSame('agent-1', $message->author?->value);
        self::assertSame(['ada', 'agent-1'], $this->participants($desk, $id));
        self::assertSame(['wait'], $desk->events->transitions);
        $assigned = $desk->events->of(TicketAssigned::class);
        self::assertSame([[$id->value, 'agent-1', 'agent-1']], array_map(static fn (TicketAssigned $event): array => [$event->conversation, $event->assignee, $event->by], $assigned));
        self::assertSame(['ada'], $desk->events->of(MessageSent::class)[1]->recipients, 'the requester is told of the answer');

        $desk->helpdesk->reply($id, Desk::id('agent-2'), 'Adding my colleague.');
        self::assertSame('agent-1', $desk->tickets->find($id)?->assignee()?->value, 'an assigned request stays with its agent');
        self::assertCount(1, $desk->events->of(TicketAssigned::class));
        self::assertSame(['wait'], $desk->events->transitions, 'a waiting request keeps waiting');
    }

    public function testTheRequestersAnswerBringsTheRequestBackToTheAgents(): void
    {
        $desk = new Desk();
        $id = $desk->open();
        $desk->helpdesk->answer($id, Desk::id('ada'), 'Also, the date is wrong.');
        self::assertSame(Status::New, $this->statusOf($desk, $id), 'a new request stays new');

        $desk->helpdesk->reply($id, Desk::id('agent-1'), 'Which date?');
        $desk->helpdesk->answer($id, Desk::id('ada'), 'The due date.');
        self::assertSame(Status::Open, $this->statusOf($desk, $id));
        self::assertSame(['agent-1'], $desk->events->of(MessageSent::class)[3]->recipients);

        $desk->helpdesk->apply($id, 'resolve');
        $desk->helpdesk->answer($id, Desk::id('ada'), 'Not fixed yet.');
        self::assertSame(['wait', 'answer', 'resolve', 'answer'], $desk->events->transitions, 'an answer reopens a resolved request');

        try {
            $desk->helpdesk->answer($id, Desk::id('bob'), 'Me too');
            self::fail('Someone answered a request of someone else.');
        } catch (TicketNotFound) {
        }
    }

    public function testAClosedRequestTakesNoMoreMessages(): void
    {
        $desk = new Desk();
        $id = $desk->open();
        $desk->helpdesk->withdraw($id, Desk::id('ada'));
        $ticket = $desk->tickets->find($id);
        self::assertSame([Status::Closed, '2026-11-13T10:00:00+00:00'], [$ticket?->status(), $ticket?->closedAt()?->format(\DATE_ATOM)]);

        foreach ([
            static fn (): Message => $desk->helpdesk->answer($id, Desk::id('ada'), 'One more thing'),
            static fn (): Message => $desk->helpdesk->reply($id, Desk::id('agent-1'), 'Hello?'),
        ] as $call) {
            try {
                $call();
                self::fail('A closed request took a message.');
            } catch (TransitionRefused $transitionRefused) {
                self::assertStringStartsWith('A request that is closed cannot be changed by "', $transitionRefused->getMessage());
            }
        }

        self::assertCount(1, $this->bodies($desk, $id));

        $this->expectException(TransitionRefused::class);
        $this->expectExceptionMessageIs('A request that is closed cannot be changed by "close".');

        $desk->helpdesk->withdraw($id, Desk::id('ada'));
    }

    public function testAgentsWorkTheirQueuesOnly(): void
    {
        $desk = new Desk(['billing']);
        $billing = $desk->open('ada', 'billing');
        $desk->clock->modify('+1 minute');
        $tech = $desk->open('bob', 'tech');

        self::assertSame(['billing'], array_map(static fn (Queue $queue): string => $queue->name->value, $desk->helpdesk->queues()));
        self::assertSame([$billing->value], $this->ids($desk->helpdesk->desk()));
        self::assertSame([], $desk->helpdesk->desk(Desk::queue('tech')));
        self::assertSame([], $desk->helpdesk->desk(Desk::queue('sales')));

        foreach ([
            static fn (): Ticket => $desk->helpdesk->forAgent($tech),
            static fn (): Message => $desk->helpdesk->reply($tech, Desk::id('agent-1'), 'Hi'),
            static fn () => $desk->helpdesk->assign($tech, Desk::id('agent-1'), Desk::id('agent-1')),
            static fn () => $desk->helpdesk->apply($tech, 'resolve'),
            static fn () => $desk->helpdesk->move($tech, Desk::queue('billing')),
            static fn () => $desk->helpdesk->move($billing, Desk::queue('tech')),
            static fn () => $desk->helpdesk->move($billing, Desk::queue('sales')),
            static fn (): Ticket => $desk->helpdesk->forAgent(ConversationId::generate()),
        ] as $index => $call) {
            try {
                $call();
                self::fail('Call '.$index." worked a request outside the agent's queues.");
            } catch (TicketNotFound) {
            }
        }

        $desk->access->queues = ['billing', 'tech'];
        self::assertSame([$tech->value, $billing->value], $this->ids($desk->helpdesk->desk()));
        $desk->access->queues = [];
        self::assertSame([], $desk->helpdesk->desk());
        self::assertSame([], $desk->helpdesk->queues());
    }

    public function testTheDeskIsFilteredByQueueStatusAndAssignee(): void
    {
        $desk = new Desk(['billing', 'tech']);
        $first = $desk->open('ada', 'billing');
        $desk->clock->modify('+1 minute');
        $second = $desk->open('bob', 'tech');
        $desk->clock->modify('+1 minute');
        $desk->helpdesk->assign($first, Desk::id('agent-1'), Desk::id('lead'));

        self::assertSame([$second->value], $this->ids($desk->helpdesk->desk(Desk::queue('tech'))));
        self::assertSame([$first->value], $this->ids($desk->helpdesk->desk(null, Status::Open)));
        self::assertSame([$second->value], $this->ids($desk->helpdesk->desk(null, Status::New)));
        self::assertSame([$first->value], $this->ids($desk->helpdesk->desk(null, null, Desk::id('agent-1'))));
        self::assertSame([$first->value, $second->value], $this->ids($desk->helpdesk->desk()), 'latest change first');
        self::assertSame([], $desk->helpdesk->desk(null, null, null, 2));
    }

    public function testAssigningJoinsTheAgentAndTakesTheRequest(): void
    {
        $desk = new Desk();
        $id = $desk->open();

        $desk->helpdesk->assign($id, Desk::id('agent-1'), Desk::id('lead'));
        $ticket = $desk->tickets->find($id);
        self::assertSame([Status::Open, 'agent-1'], [$ticket?->status(), $ticket?->assignee()?->value]);
        self::assertSame(['ada', 'agent-1'], $this->participants($desk, $id));
        self::assertSame(['take'], $desk->events->transitions);
        self::assertSame([[$id->value, 'agent-1', 'lead']], array_map(static fn (TicketAssigned $event): array => [$event->conversation, $event->assignee, $event->by], $desk->events->of(TicketAssigned::class)));

        $desk->helpdesk->assign($id, Desk::id('agent-2'), Desk::id('lead'));
        self::assertSame(['take'], $desk->events->transitions, 'an open request is not taken again');
        $desk->helpdesk->assign($id, null, Desk::id('lead'));
        self::assertNull($desk->tickets->find($id)?->assignee());
        self::assertSame([null, 'lead'], [$desk->events->of(TicketAssigned::class)[2]->assignee, $desk->events->of(TicketAssigned::class)[2]->by]);
        self::assertSame(Status::Open, $this->statusOf($desk, $id));
    }

    public function testAgentsApplyTheManualTransitions(): void
    {
        $desk = new Desk();
        $id = $desk->open();

        self::assertSame(['take', 'resolve', 'close'], $desk->helpdesk->transitions($desk->helpdesk->forAgent($id)));
        $desk->helpdesk->apply($id, 'resolve');
        self::assertSame(['reopen', 'close'], $desk->helpdesk->transitions($desk->helpdesk->forAgent($id)));
        $desk->helpdesk->apply($id, 'reopen');
        self::assertSame(Status::Open, $this->statusOf($desk, $id));

        try {
            $desk->helpdesk->apply($id, 'wait');
            self::fail('An automatic transition was applied by hand.');
        } catch (TransitionRefused $transitionRefused) {
            self::assertSame('A request that is open cannot be changed by "wait".', $transitionRefused->getMessage());
            self::assertNull($transitionRefused->getPrevious());
        }

        try {
            $desk->helpdesk->apply($id, 'take');
            self::fail('An open request was taken.');
        } catch (TransitionRefused $transitionRefused) {
            self::assertNotNull($transitionRefused->getPrevious());
        }

        $desk->helpdesk->apply($id, 'close');
        self::assertSame([], $desk->helpdesk->transitions($desk->helpdesk->forAgent($id)));
    }

    public function testAGuardOfTheApplicationHoldsATransition(): void
    {
        $desk = new Desk();
        $id = $desk->open();
        $desk->dispatcher->addListener('workflow.phil_helpdesk_ticket.guard.close', static function (GuardEvent $event): void {
            $event->setBlocked(true, 'Only after a survey.');
        });

        self::assertNotContains('close', $desk->helpdesk->transitions($desk->helpdesk->forAgent($id)));

        $this->expectException(TransitionRefused::class);

        $desk->helpdesk->apply($id, 'close');
    }

    public function testARequestMovesBetweenTheQueuesOfTheAgent(): void
    {
        $desk = new Desk(['billing', 'tech']);
        $id = $desk->open();
        $desk->clock->modify('+1 minute');

        $desk->helpdesk->move($id, Desk::queue('tech'));

        $moved = $desk->tickets->find($id);
        self::assertInstanceOf(Ticket::class, $moved);
        self::assertSame('tech', $moved->queue()->value);
        self::assertSame('2026-11-13T10:01:00+00:00', $moved->updatedAt()->format(\DATE_ATOM));
    }

    public function testACannedReplyIsWrittenForTheRequest(): void
    {
        $desk = new Desk(cannedReplies: new FixedCannedReplies());
        $id = $desk->open();

        self::assertSame(['thanks'], array_map(static fn (CannedReply $reply): string => $reply->id, $desk->helpdesk->cannedReplies($desk->helpdesk->forAgent($id))));
        $desk->helpdesk->replyWith($id, Desk::id('agent-1'), 'thanks', 'fr');
        self::assertSame('Thanks for writing to billing (fr).', $this->bodies($desk, $id)[1]);
        $desk->helpdesk->replyWith($id, Desk::id('agent-1'), 'thanks');
        self::assertSame('Thanks for writing to billing (none).', $this->bodies($desk, $id)[2]);

        $this->expectException(CannedReplyNotFound::class);

        $desk->helpdesk->replyWith($id, Desk::id('agent-1'), 'unknown');
    }

    private function statusOf(Desk $desk, ConversationId $id): ?Status
    {
        return $desk->tickets->find($id)?->status();
    }

    /**
     * @param list<Ticket> $tickets
     *
     * @return list<string>
     */
    private function ids(array $tickets): array
    {
        return array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $tickets);
    }

    public function testNothingNeedsADispatcher(): void
    {
        $desk = new Desk(['billing']);
        $helpdesk = new Helpdesk($desk->tickets, $desk->conversations, $desk->queues, $desk->access, $desk->clock, new NoCannedReplies());

        $ticket = $helpdesk->open(Desk::id('ada'), Desk::queue('billing'), 'Invoice', 'Wrong');
        $helpdesk->reply($ticket->conversation, Desk::id('agent-1'), 'On it');
        $helpdesk->assign($ticket->conversation, Desk::id('agent-2'), Desk::id('agent-1'));

        self::assertSame('agent-2', $desk->tickets->find($ticket->conversation)?->assignee()?->value);
    }

    public function testTheQueuesOfAnAgentFormAList(): void
    {
        $desk = new Desk(['tech']);

        self::assertSame(['tech'], array_map(static fn (Queue $queue): string => $queue->name->value, $desk->helpdesk->queues()));
        self::assertSame([0], array_keys($desk->helpdesk->queues()));
    }

    public function testARequestWhoseRequesterWasForgottenIsKeptAsItIs(): void
    {
        $desk = new Desk();
        $id = $desk->open('ada');
        $desk->tickets->forget(Desk::id('ada'));

        $ticket = $desk->tickets->find($id);
        self::assertInstanceOf(Ticket::class, $ticket);
        self::assertNull($ticket->requester());

        $desk->tickets->save($ticket);

        self::assertNull($desk->tickets->find($id)?->requester());
    }

    /**
     * @return list<string>
     */
    private function participants(Desk $desk, ConversationId $id): array
    {
        $active = array_filter($desk->conversations->participants($id), static fn (Participation $participation): bool => $participation->isActive());

        return array_values(array_map(static fn (Participation $participation): string => $participation->participant->value, $active));
    }

    /**
     * @return list<?string>
     */
    private function bodies(Desk $desk, ConversationId $id): array
    {
        return array_map(static fn (Message $message): ?string => $message->body, $desk->conversations->messages($id, 50));
    }
}
