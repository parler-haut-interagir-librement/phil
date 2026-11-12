<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Report;

use Phil\AnalyticsBundle\Application\Contract\ReportSender;
use Phil\AnalyticsBundle\Application\Contract\ReportSubscriptions;
use Phil\AnalyticsBundle\Domain\Report\ReportCadence;
use Phil\AnalyticsBundle\Domain\Report\ReportPeriod;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;

/**
 * The reports due on a UTC day: the weekly ones on a Monday, for the week before; the monthly ones on the first of a
 * month, for the month before. Run once a day; a day on which nothing is due sends nothing.
 */
final readonly class DueReports
{
    public function __construct(
        private ReportSubscriptions $subscriptions,
        private ReportSender $sender,
    ) {
    }

    /**
     * @return int the number of reports sent
     */
    public function sendFor(UtcDay $today): int
    {
        $sent = 0;

        foreach (self::cadencesDueOn($today) as $cadence) {
            $period = ReportPeriod::lastCompleteBefore($today, $cadence);

            foreach ($this->subscriptions->ofCadence($cadence) as $subscription) {
                $this->sender->send($subscription, $period);
                ++$sent;
            }
        }

        return $sent;
    }

    /**
     * @return list<ReportCadence>
     */
    public static function cadencesDueOn(UtcDay $today): array
    {
        $due = [];

        if ('1' === $today->start()->format('N')) {
            $due[] = ReportCadence::Weekly;
        }

        if ($today->equals(UtcMonth::of($today)->firstDay())) {
            $due[] = ReportCadence::Monthly;
        }

        return $due;
    }
}
