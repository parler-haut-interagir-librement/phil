<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application;

use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Application\Contract\QueueAccess;
use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\HelpdeskBundle\Application\Event\TicketOpened;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\Exception\TicketNotFound;
use Phil\HelpdeskBundle\Domain\Exception\TransitionRefused;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Repository\TicketRepository;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Exception\LogicException as WorkflowException;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyDispatcher;

/**
 * The entry point of the help desk. A request is a conversation of phil/conversation-bundle, opened by its requester
 * alone, with a queue, a status and an assignee: the requester answers in it; an agent of its queue reads it, answers —
 * joining the conversation —, takes it, assigns it, moves it to another queue and changes its status. Who works a queue
 * is the `QueueAccess` of the person signed in. The status moves through the workflow `phil_helpdesk_ticket`: an agent's
 * answer makes the request wait for the requester, the requester's answer brings it back to the agents.
 */
final readonly class Helpdesk
{
    public const int PAGE_SIZE = 50;

    private WorkflowInterface $workflow;

    public function __construct(
        private TicketRepository $tickets,
        private Conversations $conversations,
        private Queues $queues,
        private QueueAccess $access,
        private ClockInterface $clock,
        private CannedReplies $cannedReplies = new NoCannedReplies(),
        private ?EventDispatcherInterface $events = null,
    ) {
        $this->workflow = new StateMachine(
            TicketWorkflow::definition(),
            TicketWorkflow::markingStore(),
            $events instanceof SymfonyDispatcher ? $events : null,
            TicketWorkflow::NAME,
        );
    }

    /**
     * Opens a request in a queue, its conversation started by the requester alone.
     *
     * @throws InvalidQueue        when the queue is not declared
     * @throws InvalidConversation when the subject or the body is refused
     */
    public function open(ParticipantId $requester, QueueName $queue, string $subject, string $body): Ticket
    {
        $this->queues->get($queue);
        $conversation = $this->conversations->startOpen($requester, $subject, $body);
        $ticket = new Ticket($conversation->id, $queue, $requester, $this->clock->now());
        $this->tickets->save($ticket);
        $this->events?->dispatch(new TicketOpened($conversation->id->value, $queue->value, $requester->value));

        return $ticket;
    }

    /**
     * A request of the requester.
     *
     * @throws TicketNotFound when it does not exist or is not theirs
     */
    public function ofRequester(ConversationId $id, ParticipantId $requester): Ticket
    {
        $ticket = $this->tickets->find($id);

        if (!$ticket instanceof Ticket || !$ticket->isRequestedBy($requester)) {
            throw TicketNotFound::of($id->value);
        }

        return $ticket;
    }

    /**
     * A page of the requester's requests, latest change first.
     *
     * @return list<Ticket>
     */
    public function requests(ParticipantId $requester, int $page = 1): array
    {
        return $this->tickets->ofRequester($requester, self::PAGE_SIZE, $this->offset($page));
    }

    /**
     * The requester answers: the request comes back to the agents when it waited for them or was resolved.
     *
     * @throws TicketNotFound      when the request is not theirs
     * @throws InvalidConversation when the body is refused
     * @throws TransitionRefused   when the request is closed
     */
    public function answer(ConversationId $id, ParticipantId $requester, string $body): Message
    {
        $ticket = $this->ofRequester($id, $requester);
        $this->refuseClosed($ticket, TicketWorkflow::ANSWER);
        $message = $this->conversations->reply($id, $requester, $body);

        if ($this->workflow->can($ticket, TicketWorkflow::ANSWER)) {
            $this->workflow->apply($ticket, TicketWorkflow::ANSWER);
        }

        $this->save($ticket);

        return $message;
    }

    /**
     * The requester closes their request.
     *
     * @throws TicketNotFound    when the request is not theirs
     * @throws TransitionRefused when it is closed already
     */
    public function withdraw(ConversationId $id, ParticipantId $requester): void
    {
        $this->transit($this->ofRequester($id, $requester), TicketWorkflow::CLOSE);
    }

    /**
     * A request of a queue the agent works.
     *
     * @throws TicketNotFound when it does not exist or its queue is not theirs
     */
    public function forAgent(ConversationId $id): Ticket
    {
        $ticket = $this->tickets->find($id);

        if (!$ticket instanceof Ticket || !$this->works($ticket->queue())) {
            throw TicketNotFound::of($id->value);
        }

        return $ticket;
    }

    /**
     * A page of the requests of the queues the agent works, or of one of them, by status or assignee, latest change
     * first.
     *
     * @return list<Ticket>
     */
    public function desk(?QueueName $queue = null, ?Status $status = null, ?ParticipantId $assignee = null, int $page = 1): array
    {
        $queues = $queue instanceof QueueName ? array_filter([$queue], $this->works(...)) : $this->workedQueues();

        return [] === $queues ? [] : $this->tickets->inQueues($queues, $status, $assignee, self::PAGE_SIZE, $this->offset($page));
    }

    /**
     * The queues the agent works, in their order.
     *
     * @return list<Queue>
     */
    public function queues(): array
    {
        return array_values(array_filter($this->queues->all(), $this->access->mayWork(...)));
    }

    /**
     * An agent answers: they join the conversation, the request is assigned to them when it was to nobody, and it waits
     * for the requester.
     *
     * @throws TicketNotFound      when the request's queue is not the agent's
     * @throws InvalidConversation when the body is refused
     * @throws TransitionRefused   when the request is closed
     */
    public function reply(ConversationId $id, ParticipantId $agent, string $body): Message
    {
        $ticket = $this->forAgent($id);
        $this->refuseClosed($ticket, TicketWorkflow::WAIT);
        $this->conversations->join($id, $agent);
        $message = $this->conversations->reply($id, $agent, $body);

        if (!$ticket->assignee() instanceof ParticipantId) {
            $ticket->assign($agent);
            $this->events?->dispatch(new TicketAssigned($id->value, $agent->value, $agent->value));
        }

        if ($this->workflow->can($ticket, TicketWorkflow::WAIT)) {
            $this->workflow->apply($ticket, TicketWorkflow::WAIT);
        }

        $this->save($ticket);

        return $message;
    }

    /**
     * An agent answers with a canned reply, written for the request.
     *
     * @throws CannedReplyNotFound when the reply is not offered in the queue of the request
     */
    public function replyWith(ConversationId $id, ParticipantId $agent, string $cannedReply, ?string $locale = null): Message
    {
        return $this->reply($id, $agent, $this->cannedReplies->render($cannedReply, $this->forAgent($id), $locale));
    }

    /**
     * The canned replies offered for a request.
     *
     * @return list<CannedReply>
     */
    public function cannedReplies(Ticket $ticket): array
    {
        return $this->cannedReplies->for($ticket);
    }

    /**
     * Assigns the request to an agent, who joins its conversation, or to nobody; a new request is taken.
     *
     * @throws TicketNotFound when the request's queue is not the agent's
     */
    public function assign(ConversationId $id, ?ParticipantId $assignee, ParticipantId $by): void
    {
        $ticket = $this->forAgent($id);

        if ($assignee instanceof ParticipantId) {
            $this->conversations->join($id, $assignee);
        }

        $ticket->assign($assignee);

        if ($assignee instanceof ParticipantId && $this->workflow->can($ticket, TicketWorkflow::TAKE)) {
            $this->workflow->apply($ticket, TicketWorkflow::TAKE);
        }

        $this->save($ticket);
        $this->events?->dispatch(new TicketAssigned($id->value, $assignee?->value, $by->value));
    }

    /**
     * Moves the request to another queue the agent works.
     *
     * @throws TicketNotFound when the request's queue, or the other, is not the agent's
     * @throws InvalidQueue   when the other queue is not declared
     */
    public function move(ConversationId $id, QueueName $queue): void
    {
        $ticket = $this->forAgent($id);

        if (!$this->works($queue)) {
            throw TicketNotFound::of($id->value);
        }

        $ticket->move($queue);
        $this->save($ticket);
    }

    /**
     * Applies a transition an agent applies by hand: take, resolve, reopen or close.
     *
     * @throws TicketNotFound    when the request's queue is not the agent's
     * @throws TransitionRefused when the transition is not one of them, or not enabled
     */
    public function apply(ConversationId $id, string $transition): void
    {
        $ticket = $this->forAgent($id);

        if (!\in_array($transition, TicketWorkflow::MANUAL, true)) {
            throw TransitionRefused::of($transition, $ticket->status()->value);
        }

        $this->transit($ticket, $transition);
    }

    /**
     * The transitions an agent may apply by hand to the request now.
     *
     * @return list<string>
     */
    public function transitions(Ticket $ticket): array
    {
        return array_values(array_filter(TicketWorkflow::MANUAL, fn (string $transition): bool => $this->workflow->can($ticket, $transition)));
    }

    private function transit(Ticket $ticket, string $transition): void
    {
        try {
            $this->workflow->apply($ticket, $transition);
        } catch (WorkflowException $workflowException) {
            throw TransitionRefused::of($transition, $ticket->status()->value, $workflowException);
        }

        $this->save($ticket);
    }

    private function refuseClosed(Ticket $ticket, string $transition): void
    {
        if ($ticket->status()->isClosed()) {
            throw TransitionRefused::of($transition, $ticket->status()->value);
        }
    }

    private function save(Ticket $ticket): void
    {
        $ticket->touch($this->clock->now());
        $this->tickets->save($ticket);
    }

    private function works(QueueName $queue): bool
    {
        try {
            return $this->access->mayWork($this->queues->get($queue));
        } catch (InvalidQueue) {
            return false;
        }
    }

    /**
     * @return list<QueueName>
     */
    private function workedQueues(): array
    {
        return array_map(static fn (Queue $queue): QueueName => $queue->name, $this->queues());
    }

    /**
     * @return int<0, max>
     */
    private function offset(int $page): int
    {
        return (max(1, $page) - 1) * self::PAGE_SIZE;
    }
}
