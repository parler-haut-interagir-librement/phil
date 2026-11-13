<?php

declare(strict_types=1);

namespace Phil\Automations\Messenger;

use Override;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `automations` of Symfony Scheduler: a `ResumeDueRuns` every minute by default, which picks up the
 * delays and the retries whose time has come, whatever the transport did with their delayed message.
 */
#[AsSchedule('automations')]
final readonly class AutomationsSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private string $frequency = '1 minute',
        private int $limit = 500,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every($this->frequency, new ResumeDueRuns($this->limit)));
    }
}
