<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Report;

use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * Who receives which report. The recipient is the application's opaque reference: an address never travels in a
 * message, the sender resolves it.
 */
final readonly class ReportSubscription
{
    public function __construct(
        public string $id,
        public SiteId $site,
        public ReportCadence $cadence,
        public string $recipient,
    ) {
    }
}
