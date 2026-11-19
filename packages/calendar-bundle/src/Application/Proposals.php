<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Events proposed by members: made from a draft as any event, kept out of the calendar until a moderator approves
 * them, through the workflow `phil_calendar_proposal`. Who may propose and who may approve is the application's: its
 * access control, and its guards on the workflow.
 */
final readonly class Proposals
{
    private WorkflowInterface $workflow;

    public function __construct(
        private Calendar $calendar,
        private EventRepository $events,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->workflow = new StateMachine(ProposalWorkflow::definition(), ProposalWorkflow::markingStore(), $dispatcher, ProposalWorkflow::NAME);
    }

    /**
     * A member's event, saved as a proposal, out of the calendar until it is approved.
     */
    public function propose(EventDraft $draft, Member $member): Event
    {
        return $this->calendar->schedule($draft, $member);
    }

    /**
     * Publishes a proposal, or one refused before.
     *
     * @throws NotEnabledTransitionException when it is published already, or a guard holds it
     */
    public function approve(Event $event): void
    {
        $this->apply($event, ProposalWorkflow::APPROVE);
    }

    /**
     * @throws NotEnabledTransitionException when it is not a proposal, or a guard holds it
     */
    public function refuse(Event $event): void
    {
        $this->apply($event, ProposalWorkflow::REFUSE);
    }

    /**
     * Whether a moderator can approve, or refuse, the event now.
     */
    public function can(Event $event, string $transition): bool
    {
        return $this->workflow->can($event, $transition);
    }

    /**
     * The proposals waiting, by their start.
     *
     * @return list<Event>
     */
    public function waiting(int $limit = 100): array
    {
        return $this->events->proposed(max(1, min(500, $limit)));
    }

    private function apply(Event $event, string $transition): void
    {
        $this->workflow->apply($event, $transition);
        $this->events->save($event);
    }
}
