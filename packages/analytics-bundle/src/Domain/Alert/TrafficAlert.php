<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Alert;

use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;

final readonly class TrafficAlert
{
    public function __construct(
        public SiteId $site,
        public UtcDay $day,
        public TrafficAlertKind $kind,
        public int $count,
        public int $baseline,
    ) {
    }
}
