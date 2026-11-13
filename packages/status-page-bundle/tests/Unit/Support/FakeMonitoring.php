<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Support;

use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The surveillance of phil/monitoring-bundle as a test sets it, counting the rounds of checks it was asked for.
 */
final class FakeMonitoring implements MonitoringStatus
{
    public int $rounds = 0;

    /**
     * @param list<HeartbeatStatus> $heartbeats
     */
    public function __construct(
        public HealthReport $report,
        public array $heartbeats = [],
        public ?MaintenanceState $maintenance = null,
    ) {
    }

    #[Override]
    public function health(): HealthReport
    {
        ++$this->rounds;

        return $this->report;
    }

    #[Override]
    public function heartbeats(): array
    {
        return $this->heartbeats;
    }

    #[Override]
    public function maintenance(): ?MaintenanceState
    {
        return $this->maintenance;
    }
}
