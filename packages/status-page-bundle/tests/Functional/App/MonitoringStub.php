<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Functional\App;

use DateTimeImmutable;
use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The read model of phil/monitoring-bundle, standing for its bundle: the database is down.
 */
final readonly class MonitoringStub implements MonitoringStatus
{
    #[Override]
    public function health(): HealthReport
    {
        return new HealthReport([new CheckResult('database', CheckOutcome::down('unreachable'), 3)], new DateTimeImmutable());
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
