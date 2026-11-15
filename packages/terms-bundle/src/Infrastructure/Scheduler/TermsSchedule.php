<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Scheduler;

use Override;
use Phil\TermsBundle\Application\Message\SendTermsReminders;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_terms`, run by `messenger:consume scheduler_phil_terms`: the reminders at the configured
 * frequency, such as `1 week`.
 */
final class TermsSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_terms';

    private ?Schedule $schedule = null;

    public function __construct(
        private readonly string $frequency = '1 week',
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        return $this->schedule ??= new Schedule()->add(RecurringMessage::every($this->frequency, new SendTermsReminders()));
    }
}
