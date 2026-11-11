<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Phil\MonitoringBundle\Domain\Contract\MaintenanceStore;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The maintenance mode in memory.
 */
final class InMemoryMaintenance implements MaintenanceStore
{
    public function __construct(
        public ?MaintenanceState $state = null,
    ) {
    }

    #[Override]
    public function current(): ?MaintenanceState
    {
        return $this->state;
    }

    #[Override]
    public function enable(MaintenanceState $state): void
    {
        $this->state = $state;
    }

    #[Override]
    public function disable(): void
    {
        $this->state = null;
    }
}
