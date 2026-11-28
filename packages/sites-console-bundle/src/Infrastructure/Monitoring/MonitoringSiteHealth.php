<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Monitoring;

use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\CheckState;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;

/**
 * The health of the site from the checks of phil/monitoring-bundle, run when the console asks.
 */
final readonly class MonitoringSiteHealth implements SiteHealth
{
    public function __construct(
        private MonitoringStatus $monitoring,
    ) {
    }

    #[Override]
    public function summary(): HealthSummary
    {
        $report = $this->monitoring->health();
        $checks = array_map(static fn (CheckResult $result): CheckState => new CheckState($result->name, self::state($result->status()->value), $result->outcome->summary), $report->results);

        return new HealthSummary(self::state($report->status()->value), $checks);
    }

    private static function state(string $status): HealthState
    {
        return HealthState::tryFrom($status) ?? HealthState::Unknown;
    }
}
