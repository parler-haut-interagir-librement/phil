<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\View;

use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\Alert;

/**
 * A site, its latest snapshot and its alerts, as the dashboard shows them.
 */
final readonly class SiteOverview
{
    /**
     * @param list<Alert> $alerts
     */
    public function __construct(
        public Site $site,
        public ?Snapshot $snapshot,
        public array $alerts,
    ) {
    }

    public function health(): HealthState
    {
        return $this->snapshot?->health()->state ?? HealthState::Unknown;
    }
}
