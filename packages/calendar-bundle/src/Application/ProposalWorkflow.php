<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Model\Event;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;

/**
 * The state machine of a member's proposal, `phil_calendar_proposal`: proposed, then approved — published — or
 * refused; a refused proposal can be approved later. Its events are those of `symfony/workflow`, so a guard of the
 * application can hold an approval, and a listener follow it — a message to the member.
 */
final class ProposalWorkflow
{
    public const string NAME = 'phil_calendar_proposal';

    public const string APPROVE = 'approve';

    public const string REFUSE = 'refuse';

    public static function definition(): Definition
    {
        return new Definition(
            [Event::PROPOSED, Event::PUBLISHED, Event::REFUSED],
            [
                // A state machine names a transition once per state it leaves.
                new Transition(self::APPROVE, Event::PROPOSED, Event::PUBLISHED),
                new Transition(self::APPROVE, Event::REFUSED, Event::PUBLISHED),
                new Transition(self::REFUSE, Event::PROPOSED, Event::REFUSED),
            ],
            Event::PROPOSED,
        );
    }

    /**
     * The state kept by the event's `getProposalState()` and `setProposalState()`.
     */
    public static function markingStore(): MarkingStoreInterface
    {
        return new MethodMarkingStore(true, 'proposalState');
    }
}
