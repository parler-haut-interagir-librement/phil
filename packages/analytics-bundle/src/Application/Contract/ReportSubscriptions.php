<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\Report\ReportCadence;
use Phil\AnalyticsBundle\Domain\Report\ReportSubscription;

interface ReportSubscriptions
{
    /**
     * @return iterable<ReportSubscription>
     */
    public function ofCadence(ReportCadence $cadence): iterable;
}
