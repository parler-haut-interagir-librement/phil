<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;

/**
 * The economic order quantity: the number of units an order should hold so that ordering and holding together cost
 * the least, √(2 × annual demand × cost of an order ÷ cost of holding a unit a year).
 *
 * The two costs are inputs the application gives, in any one currency; the library keeps no cost.
 */
final readonly class EconomicOrderQuantity
{
    public function __construct(
        public float $annualDemand,
        public float $orderCost,
        public float $annualHoldingCost,
    ) {
        Check::positive('annual demand', $annualDemand);
        Check::positive('cost of an order', $orderCost);
        Check::positive('annual cost of holding a unit', $annualHoldingCost);

        if ($this->exact() > Check::MAX_UNITS) {
            throw InvalidInput::because('economic order quantity', 'must not exceed 1,000,000,000,000 units');
        }
    }

    /**
     * The exact quantity, in fractions of a unit.
     */
    public function exact(): float
    {
        return sqrt(2.0 * $this->annualDemand * $this->orderCost / $this->annualHoldingCost);
    }

    /**
     * The quantity in whole units, rounded up to a multiple of the pack size.
     */
    public function quantity(int $packSize = 1): int
    {
        Check::units('pack size', $packSize, 1);

        return Check::ceil($this->exact() / $packSize) * $packSize;
    }
}
