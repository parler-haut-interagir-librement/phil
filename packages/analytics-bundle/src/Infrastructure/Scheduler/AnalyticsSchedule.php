<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\AnalyticsBundle\Application\Message\ApplyCounterRetention;
use Phil\AnalyticsBundle\Application\Message\CheckBrokenFlows;
use Phil\AnalyticsBundle\Application\Message\CheckTrafficAlerts;
use Phil\AnalyticsBundle\Application\Message\SendDueReports;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The schedule `phil_analytics`, run by `messenger:consume scheduler_phil_analytics`: once a day at the configured UTC
 * time, the retention of the counters, the reports due that day, the check of the watched flows and of the traffic
 * of the watched sites, each when enabled.
 */
final readonly class AnalyticsSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'phil_analytics';

    private const string DAILY = '1 day';

    /**
     * @param string $time the UTC time of day the jobs run at, `HH:MM`
     */
    public function __construct(
        private string $time,
        private bool $retention,
        private bool $reports,
        private bool $brokenFlows,
        private bool $trafficAlerts,
    ) {
    }

    #[Override]
    public function getSchedule(): Schedule
    {
        // A time alone is that time today.
        $from = new DateTimeImmutable($this->time, new DateTimeZone('UTC'));
        $schedule = new Schedule();

        foreach ([
            [$this->retention, new ApplyCounterRetention()],
            [$this->reports, new SendDueReports()],
            [$this->brokenFlows, new CheckBrokenFlows()],
            [$this->trafficAlerts, new CheckTrafficAlerts()],
        ] as [$enabled, $message]) {
            if ($enabled) {
                $schedule->add(RecurringMessage::every(self::DAILY, $message, $from));
            }
        }

        return $schedule;
    }
}
