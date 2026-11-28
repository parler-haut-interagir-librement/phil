<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;

/**
 * The health a site reports to the console: the checks of phil/monitoring-bundle when it is installed.
 */
interface SiteHealth
{
    public function summary(): HealthSummary;
}
