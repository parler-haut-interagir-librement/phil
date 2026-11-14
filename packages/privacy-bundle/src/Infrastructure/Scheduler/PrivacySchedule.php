<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Scheduler;

use Override;
use Phil\PrivacyBundle\Application\Message\ProcessDueAccountDeletions;
use Phil\PrivacyBundle\Application\Message\RunRetention;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_privacy`, run by `messenger:consume scheduler_phil_privacy`: the account deletions and the
 * retention schedule, each at its configured frequency, such as `15 minutes` and `1 day`.
 */
final class PrivacySchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_privacy';

    private ?Schedule $schedule = null;

    public function __construct(
        private readonly ?string $deletionsFrequency,
        private readonly ?string $retentionFrequency,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        if ($this->schedule instanceof Schedule) {
            return $this->schedule;
        }

        $schedule = new Schedule();

        if (null !== $this->deletionsFrequency) {
            $schedule->add(RecurringMessage::every($this->deletionsFrequency, new ProcessDueAccountDeletions()));
        }

        if (null !== $this->retentionFrequency) {
            $schedule->add(RecurringMessage::every($this->retentionFrequency, new RunRetention()));
        }

        return $this->schedule = $schedule;
    }
}
