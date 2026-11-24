<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\SurveyBundle\Application\Message\PurgeSurveyResponses;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The schedule `phil_survey`, run by `messenger:consume scheduler_phil_survey`: the purge of the responses past their
 * retention every day at the configured time and time zone. With a lock factory, one worker runs it however many
 * consume the schedule; with a cache pool, the schedule keeps its last run, so that a worker that restarts neither
 * repeats nor skips it.
 */
final class SurveySchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_survey';

    private ?Schedule $schedule = null;

    /**
     * @param string $at the time of the purge, `HH:MM`
     */
    public function __construct(
        private readonly string $at = '03:45',
        private readonly string $timezone = 'UTC',
        private readonly ?LockFactory $locks = null,
        private readonly ?CacheInterface $state = null,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        if ($this->schedule instanceof Schedule) {
            return $this->schedule;
        }

        $schedule = new Schedule()->add(RecurringMessage::every('1 day', new PurgeSurveyResponses(), new DateTimeImmutable($this->at, new DateTimeZone($this->timezone))));

        if ($this->locks instanceof LockFactory) {
            $schedule->lock($this->locks->createLock('phil_survey.schedule'));
        }

        if ($this->state instanceof CacheInterface) {
            $schedule->stateful($this->state);
        }

        return $this->schedule = $schedule;
    }
}
