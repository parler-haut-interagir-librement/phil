<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Stock;

use Phil\InventoryAlerts\Internal\Check;

/**
 * The units of a SKU at a location, as the stock source reads them: on hand, reserved for orders, and on order from
 * suppliers.
 */
final readonly class StockLevel
{
    public function __construct(
        public string $sku,
        public string $location,
        public int $onHand,
        public int $reserved = 0,
        public int $onOrder = 0,
    ) {
        Check::code('SKU', $sku);
        Check::code('location', $location);
        Check::units('quantity on hand', $onHand);
        Check::units('reserved quantity', $reserved);
        Check::units('quantity on order', $onOrder);
    }

    public static function empty(string $sku, string $location): self
    {
        return new self($sku, $location, 0);
    }

    /**
     * The units that can still be sold: on hand less reserved, negative when more is reserved than held.
     */
    public function available(): int
    {
        return $this->onHand - $this->reserved;
    }

    /**
     * The inventory position replenishment decides on: the available units and those already on order.
     */
    public function position(): int
    {
        return $this->available() + $this->onOrder;
    }
}
