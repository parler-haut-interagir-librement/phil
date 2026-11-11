<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Contract;

use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * Where the maintenance mode is kept: read on every request, so a store answers fast, and shared by every worker and
 * server that should go into maintenance together.
 */
interface MaintenanceStore
{
    /**
     * The maintenance in force, or null when the site is open.
     */
    public function current(): ?MaintenanceState;

    public function enable(MaintenanceState $state): void;

    public function disable(): void;
}
