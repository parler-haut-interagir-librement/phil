<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\FollowBundle\Application\Message\PurgeFollowRecords;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The schedule `phil_follow`, run by `messenger:consume scheduler_phil_follow`: the purge every day at the configured
 * time and time zone. With a lock factory, one worker runs it however many consume the schedule; with a cache pool,
 * the schedule keeps its last run, so that a worker that restarts neither repeats nor skips it.
 */
final class FollowSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_follow';

    private ?Schedule $schedule = null;

    /**
     * @param string $at the time of the purge, `HH:MM`
     */
    public function __construct(
        private readonly string $at = '04:15',
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

        $schedule = new Schedule()->add(RecurringMessage::every('1 day', new PurgeFollowRecords(), new DateTimeImmutable($this->at, new DateTimeZone($this->timezone))));

        if ($this->locks instanceof LockFactory) {
            $schedule->lock($this->locks->createLock('phil_follow.schedule'));
        }

        if ($this->state instanceof CacheInterface) {
            $schedule->stateful($this->state);
        }

        return $this->schedule = $schedule;
    }
}
