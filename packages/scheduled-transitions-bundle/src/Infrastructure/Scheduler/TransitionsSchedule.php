<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler;

use Override;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_transitions`: a `RunDueTransitions` at each tick, for `messenger:consume scheduler_phil_transitions`.
 */
final readonly class TransitionsSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_transitions';

    public function __construct(
        private string $frequency = '1 minute',
        private int $limit = 100,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every($this->frequency, new RunDueTransitions($this->limit)));
    }
}
