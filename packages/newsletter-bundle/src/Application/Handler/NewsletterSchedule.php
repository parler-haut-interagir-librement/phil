<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use Override;
use Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns;
use Phil\NewsletterBundle\Application\Message\FetchBounces;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `newsletter` of Symfony Scheduler: the scheduled campaigns started every minute, and, with a bounce
 * mailbox, the mailbox read every five minutes.
 */
#[AsSchedule('newsletter')]
final readonly class NewsletterSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private bool $bounceMailbox = false,
        private string $campaignFrequency = '1 minute',
        private string $bounceFrequency = '5 minutes',
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule()->add(RecurringMessage::every($this->campaignFrequency, new DispatchDueCampaigns()));

        if ($this->bounceMailbox) {
            $schedule->add(RecurringMessage::every($this->bounceFrequency, new FetchBounces()));
        }

        return $schedule;
    }
}
