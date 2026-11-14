<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\NotificationsBundle\Application\Message\PurgeNotifications;
use Phil\NotificationsBundle\Application\Message\SendDigests;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_notifications`, run by `messenger:consume scheduler_phil_notifications`: the daily digests every
 * day at the configured time, the weekly digests on the configured day at that time, and the retention purge every day
 * at the configured time of the purge, all in the configured time zone.
 */
final class NotificationSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_notifications';

    private ?Schedule $schedule = null;

    /**
     * @param string $at      the time of the digests, `HH:MM`
     * @param string $weekday the day of the weekly digest, in English
     * @param string $purgeAt the time of the purge, `HH:MM`
     */
    public function __construct(
        private readonly string $at = '07:00',
        private readonly string $weekday = 'monday',
        private readonly string $purgeAt = '03:30',
        private readonly string $timezone = 'UTC',
        private readonly bool $digests = true,
        private readonly bool $purge = true,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        if ($this->schedule instanceof Schedule) {
            return $this->schedule;
        }

        $zone = new DateTimeZone($this->timezone);
        $schedule = new Schedule();

        if ($this->digests) {
            $schedule->add(
                RecurringMessage::every('1 day', new SendDigests(DigestFrequency::Daily), new DateTimeImmutable($this->at, $zone)),
                RecurringMessage::every('1 week', new SendDigests(DigestFrequency::Weekly), new DateTimeImmutable(\sprintf('%s %s', $this->weekday, $this->at), $zone)),
            );
        }

        if ($this->purge) {
            $schedule->add(RecurringMessage::every('1 day', new PurgeNotifications(), new DateTimeImmutable($this->purgeAt, $zone)));
        }

        return $this->schedule = $schedule;
    }
}
