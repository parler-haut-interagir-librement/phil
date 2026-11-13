<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Scheduler;

use Override;
use Phil\StatusPageBundle\Application\Message\PushStatusSnapshot;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_status_page`, run by `messenger:consume scheduler_phil_status_page`: a snapshot pushed to every
 * target at the configured frequency, such as `5 minutes`.
 */
final readonly class StatusPageSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_status_page';

    public function __construct(
        private string $frequency,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every($this->frequency, new PushStatusSnapshot()));
    }
}
