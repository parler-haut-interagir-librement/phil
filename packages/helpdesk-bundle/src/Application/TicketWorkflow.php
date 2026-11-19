<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application;

use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;

/**
 * The state machine of a request, `phil_helpdesk_ticket`: new until an agent takes it; open while the agents work;
 * waiting once an agent answered, until the requester does; resolved, which the requester's answer or an agent reopens;
 * closed, for good. Its events are those of `symfony/workflow`, so a guard of the application can hold a transition,
 * and a listener follow it.
 */
final class TicketWorkflow
{
    public const string NAME = 'phil_helpdesk_ticket';

    /** An agent takes a new request. */
    public const string TAKE = 'take';

    /** An agent answered: the request waits for the requester. */
    public const string WAIT = 'wait';

    /** The requester answered: the request is back with the agents. */
    public const string ANSWER = 'answer';

    public const string RESOLVE = 'resolve';

    public const string REOPEN = 'reopen';

    public const string CLOSE = 'close';

    /** The transitions an agent applies by hand, from the page of a request. */
    public const array MANUAL = [self::TAKE, self::RESOLVE, self::REOPEN, self::CLOSE];

    private function __construct()
    {
    }

    public static function definition(): Definition
    {
        $new = Status::New->value;
        $open = Status::Open->value;
        $waiting = Status::Waiting->value;
        $resolved = Status::Resolved->value;
        $closed = Status::Closed->value;

        return new Definition(
            array_map(static fn (Status $status): string => $status->value, Status::cases()),
            [
                // A state machine names a transition once per state it leaves.
                new Transition(self::TAKE, $new, $open),
                new Transition(self::WAIT, $new, $waiting),
                new Transition(self::WAIT, $open, $waiting),
                new Transition(self::ANSWER, $waiting, $open),
                new Transition(self::ANSWER, $resolved, $open),
                new Transition(self::RESOLVE, $new, $resolved),
                new Transition(self::RESOLVE, $open, $resolved),
                new Transition(self::RESOLVE, $waiting, $resolved),
                new Transition(self::REOPEN, $resolved, $open),
                new Transition(self::CLOSE, $new, $closed),
                new Transition(self::CLOSE, $open, $closed),
                new Transition(self::CLOSE, $waiting, $closed),
                new Transition(self::CLOSE, $resolved, $closed),
            ],
            $new,
        );
    }

    /**
     * The status kept by the request's `getMarking()` and `setMarking()`.
     */
    public static function markingStore(): MarkingStoreInterface
    {
        return new MethodMarkingStore(true, 'marking');
    }
}
