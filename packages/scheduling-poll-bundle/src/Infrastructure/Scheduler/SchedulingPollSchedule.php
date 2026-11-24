<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Scheduler;

use Override;
use Phil\SchedulingPollBundle\Application\Message\CloseDuePolls;
use Phil\SchedulingPollBundle\Application\Message\EraseRespondents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The bundle's schedule, `phil_scheduling_poll`: the polls past their deadline closed at the frequency of the
 * configuration, and the names and addresses past their delay erased every hour. The schedule keeps its state in the
 * cache, so that a restarted worker does not run the missed messages twice, and holds a lock when the application has
 * a lock factory, so that two workers of the schedule do not run it together; the handlers are idempotent all the same.
 */
final readonly class SchedulingPollSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_scheduling_poll';

    public function __construct(
        private CacheInterface $cache,
        private ?LockFactory $locks = null,
        private string $frequency = '5 minutes',
        private string $erasure = '1 hour',
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule()
            ->add(
                RecurringMessage::every($this->frequency, new CloseDuePolls()),
                RecurringMessage::every($this->erasure, new EraseRespondents()),
            )
            ->stateful($this->cache);

        return $this->locks instanceof LockFactory ? $schedule->lock($this->locks->createLock('phil_scheduling_poll_schedule')) : $schedule;
    }
}
