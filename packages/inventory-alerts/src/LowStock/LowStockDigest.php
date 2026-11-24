<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\LowStock;

/**
 * The low-stock alerts of one location, sent together to its staff.
 */
final readonly class LowStockDigest
{
    /**
     * @param non-empty-list<LowStockAlert> $alerts in the order of the movements
     */
    public function __construct(
        public string $location,
        public array $alerts,
    ) {
    }

    /**
     * The identifiers of the movements that raised the alerts, for a message put on a queue.
     *
     * @return non-empty-list<string>
     */
    public function movementIds(): array
    {
        return array_map(static fn (LowStockAlert $alert): string => $alert->movement->id, $this->alerts);
    }
}
