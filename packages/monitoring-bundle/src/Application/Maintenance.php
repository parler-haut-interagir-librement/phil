<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application;

use Phil\MonitoringBundle\Domain\Contract\MaintenanceStore;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Psr\Clock\ClockInterface;

/**
 * Puts the site into maintenance and takes it out: visitors outside the allow-lists get a 503 with `Retry-After` until
 * it is disabled.
 */
final readonly class Maintenance
{
    public function __construct(
        private MaintenanceStore $store,
        private ClockInterface $clock,
        private int $defaultRetryAfterSeconds,
    ) {
    }

    /**
     * Enables the maintenance from now, visitors told to come back in `$retryAfterSeconds`, the configured delay when
     * null.
     */
    public function enable(?int $retryAfterSeconds = null): MaintenanceState
    {
        $state = new MaintenanceState($this->clock->now(), $retryAfterSeconds ?? $this->defaultRetryAfterSeconds);
        $this->store->enable($state);

        return $state;
    }

    public function disable(): void
    {
        $this->store->disable();
    }

    public function current(): ?MaintenanceState
    {
        return $this->store->current();
    }
}
