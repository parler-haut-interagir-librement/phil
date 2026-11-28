<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Monitoring;

use Override;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;

/**
 * The health of a site without phil/monitoring-bundle: unknown.
 */
final readonly class UnknownSiteHealth implements SiteHealth
{
    #[Override]
    public function summary(): HealthSummary
    {
        return HealthSummary::unknown();
    }
}
