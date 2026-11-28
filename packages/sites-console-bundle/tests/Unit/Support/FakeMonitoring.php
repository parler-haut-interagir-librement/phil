<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The read model of phil/monitoring-bundle with a fixed report.
 */
final readonly class FakeMonitoring implements MonitoringStatus
{
    public function __construct(
        private HealthReport $report,
    ) {
    }

    #[Override]
    public function health(): HealthReport
    {
        return $this->report;
    }

    #[Override]
    public function heartbeats(): array
    {
        return [];
    }

    #[Override]
    public function maintenance(): ?MaintenanceState
    {
        return null;
    }
}
