<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Scheduler;

use Override;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_outbox`: a `RelayOutbox` message every few seconds, which a worker of the transport
 * `scheduler_phil_outbox` hands to `RelayOutboxHandler`.
 */
final readonly class OutboxSchedule implements ScheduleProviderInterface
{
    /** The name of the schedule. */
    public const string NAME = 'phil_outbox';

    public function __construct(
        private int $frequency,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every(\sprintf('%d seconds', $this->frequency), new RelayOutbox()));
    }
}
