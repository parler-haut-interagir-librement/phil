<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application;

use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The read model of the site's surveillance, from the checker, the heartbeats and the maintenance mode.
 */
final readonly class Monitoring implements MonitoringStatus
{
    public function __construct(
        private HealthChecker $checker,
        private Heartbeats $heartbeats,
        private Maintenance $maintenance,
    ) {
    }

    #[Override]
    public function health(): HealthReport
    {
        return $this->checker->report();
    }

    #[Override]
    public function heartbeats(): array
    {
        return $this->heartbeats->statuses();
    }

    #[Override]
    public function maintenance(): ?MaintenanceState
    {
        return $this->maintenance->current();
    }
}
