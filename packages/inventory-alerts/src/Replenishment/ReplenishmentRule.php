<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;

/**
 * How a SKU is replenished at a location, and from which supplier: up to a maximum (min/max), or by a fixed quantity
 * such as the economic order quantity, when the inventory position falls to the reorder point.
 */
final readonly class ReplenishmentRule
{
    private function __construct(
        public string $sku,
        public string $location,
        public string $supplier,
        public int $reorderPoint,
        public ?int $maximum,
        public ?int $orderQuantity,
        public int $packSize,
    ) {
        Check::code('SKU', $sku);
        Check::code('location', $location);
        Check::code('supplier', $supplier);
        Check::units('reorder point', $reorderPoint);
        Check::units('pack size', $packSize, 1);
    }

    /**
     * At or under the minimum, order up to the maximum.
     */
    public static function minMax(string $sku, string $location, string $supplier, int $minimum, int $maximum, int $packSize = 1): self
    {
        if ($maximum <= $minimum) {
            throw InvalidInput::because('maximum', 'must be above the minimum');
        }

        Check::units('maximum', $maximum, 1);

        return new self($sku, $location, $supplier, $minimum, $maximum, null, $packSize);
    }

    /**
     * At or under the reorder point, order the quantity — as many times as it takes to rise above the reorder point.
     */
    public static function fixedQuantity(string $sku, string $location, string $supplier, int $reorderPoint, int $orderQuantity, int $packSize = 1): self
    {
        Check::units('order quantity', $orderQuantity, 1);

        return new self($sku, $location, $supplier, $reorderPoint, null, $orderQuantity, $packSize);
    }

    /**
     * The units to order for an inventory position, rounded up to the pack size; 0 above the reorder point.
     */
    public function quantityFor(int $position): int
    {
        if ($position > $this->reorderPoint) {
            return 0;
        }

        if (null !== $this->maximum) {
            $needed = $this->maximum - $position;
        } else {
            $quantity = (int) $this->orderQuantity;
            $needed = intdiv($this->reorderPoint - $position, $quantity) * $quantity + $quantity;
        }

        return intdiv($needed + $this->packSize - 1, $this->packSize) * $this->packSize;
    }
}
