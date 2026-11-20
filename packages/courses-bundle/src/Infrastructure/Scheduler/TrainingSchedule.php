<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\CoursesBundle\Application\Message\RunTraining;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_courses_training`, run by `messenger:consume scheduler_phil_courses_training`: a run of the
 * mandatory training every day at the configured time, in the site's time zone.
 */
final class TrainingSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_courses_training';

    private ?Schedule $schedule = null;

    /**
     * @param string $at the time of the run, `HH:MM`
     */
    public function __construct(
        private readonly string $at = '06:00',
        private readonly string $timezone = 'UTC',
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return $this->schedule ??= new Schedule()->add(RecurringMessage::every('1 day', new RunTraining(), new DateTimeImmutable($this->at, new DateTimeZone($this->timezone))));
    }
}
