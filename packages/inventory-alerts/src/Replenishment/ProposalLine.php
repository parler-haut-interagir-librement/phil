<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

/**
 * A line of a purchase proposal: the units of a SKU to bring to a location, with the position and the reorder point
 * that called for them.
 */
final readonly class ProposalLine
{
    public function __construct(
        public string $sku,
        public string $location,
        public int $quantity,
        public int $position,
        public int $reorderPoint,
    ) {
    }
}
