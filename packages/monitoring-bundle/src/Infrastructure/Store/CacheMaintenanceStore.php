<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Store;

use Override;
use Phil\MonitoringBundle\Domain\Contract\MaintenanceStore;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;

/**
 * The maintenance mode as a key of a PSR-6 cache pool, for servers that share no disk but share a pool, such as
 * Valkey or Redis. The key never expires; a pool that evicts it ends the maintenance.
 */
final readonly class CacheMaintenanceStore implements MaintenanceStore
{
    public const string KEY = 'phil_monitoring.maintenance';

    public function __construct(
        private CacheItemPoolInterface $pool,
        private ClockInterface $clock,
        private int $defaultRetryAfterSeconds,
    ) {
    }

    #[Override]
    public function current(): ?MaintenanceState
    {
        $item = $this->pool->getItem(self::KEY);

        if (!$item->isHit()) {
            return null;
        }

        return MaintenanceRecord::decode($item->get(), new MaintenanceState($this->clock->now(), $this->defaultRetryAfterSeconds));
    }

    #[Override]
    public function enable(MaintenanceState $state): void
    {
        $this->pool->save($this->pool->getItem(self::KEY)->set(MaintenanceRecord::encode($state)));
    }

    #[Override]
    public function disable(): void
    {
        $this->pool->deleteItem(self::KEY);
    }
}
