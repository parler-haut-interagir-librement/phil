<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Scheduler;

use Override;
use Phil\ReportingBundle\Application\Message\RunScheduledReport;
use Phil\ReportingBundle\Application\Report\Schedules;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_reporting` of Symfony Scheduler: a `RunScheduledReport` naming each configured schedule, every
 * `every` from its `from`. Run it with `messenger:consume scheduler_phil_reporting`.
 */
final readonly class ReportScheduleProvider implements ScheduleProviderInterface
{
    public const string NAME = 'phil_reporting';

    public function __construct(
        private Schedules $schedules,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        foreach ($this->schedules->all() as $name => $report) {
            $schedule->add(RecurringMessage::every($report->every, new RunScheduledReport($name), $report->from));
        }

        return $schedule;
    }
}
