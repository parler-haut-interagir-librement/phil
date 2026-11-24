<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Stock;

use DateTimeImmutable;
use Phil\InventoryAlerts\Internal\Check;

/**
 * A change of the stock of a SKU at a location — a sale, a reservation, a count, a transfer —, by the identifier the
 * stock source gave it. A low-stock alert names the movement that raised it.
 */
final readonly class StockMovement
{
    public function __construct(
        public string $id,
        public string $sku,
        public string $location,
        public int $change,
        public DateTimeImmutable $movedAt,
    ) {
        Check::code('movement identifier', $id);
        Check::code('SKU', $sku);
        Check::code('location', $location);
    }
}
