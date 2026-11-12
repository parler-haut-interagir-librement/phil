<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\Report\ReportPeriod;
use Phil\AnalyticsBundle\Domain\Report\ReportSubscription;

/**
 * Sends one report. It reads the counters through the small-number rule and never the private metrics of a shared view.
 */
interface ReportSender
{
    public function send(ReportSubscription $subscription, ReportPeriod $period): void;
}
