<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Report;

use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;

/**
 * The days a report covers: the last whole week (Monday to Sunday, UTC) or the last whole month before a day. A report
 * reads the counters through the small-number rule, like any other reader.
 */
final readonly class ReportPeriod
{
    private function __construct(
        public ReportCadence $cadence,
        public UtcDay $from,
        public UtcDay $to,
    ) {
    }

    public static function lastCompleteBefore(UtcDay $today, ReportCadence $cadence): self
    {
        if (ReportCadence::Monthly === $cadence) {
            $thisMonth = UtcMonth::of($today);

            return new self($cadence, $thisMonth->minus(1)->firstDay(), UtcDay::of($thisMonth->firstDay()->start()->modify('-1 day')));
        }

        $start = $today->start();
        $monday = $start->setISODate((int) $start->format('o'), (int) $start->format('W'));

        return new self($cadence, UtcDay::of($monday->modify('-7 days')), UtcDay::of($monday->modify('-1 day')));
    }
}
