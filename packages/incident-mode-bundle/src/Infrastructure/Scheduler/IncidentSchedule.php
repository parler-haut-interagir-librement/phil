<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Scheduler;

use Override;
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Phil\IncidentModeBundle\Infrastructure\DependencyInjection\IncidentModeServices;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The recurring sweep of Symfony Scheduler: `LiftExpiredIncidents` at the configured frequency, which records the end
 * of every incident whose delayed message a transport lost. A worker consumes it from the transport
 * `scheduler_<name>`; two workers lifting the same incident send its event once.
 */
final readonly class IncidentSchedule implements ScheduleProviderInterface
{
    public const string DEFAULT_NAME = IncidentModeServices::DEFAULT_SCHEDULE_NAME;

    public const string DEFAULT_FREQUENCY = IncidentModeServices::DEFAULT_SCHEDULE_FREQUENCY;

    public function __construct(
        private string $frequency = self::DEFAULT_FREQUENCY,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every($this->frequency, new LiftExpiredIncidents()));
    }
}
