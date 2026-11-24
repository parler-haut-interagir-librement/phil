<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\ValueObject;

use Phil\Money\Money;

/**
 * What the units of a SKU that left with a sale cost the shop, less those that came back: the cost of goods sold of
 * one order line. `uncosted` counts the units among them whose cost is unknown, which add nothing to the cost.
 */
final readonly class SaleCost
{
    public function __construct(
        public string $reference,
        public string $sku,
        public int $units,
        public int $uncosted,
        public Money $cost,
    ) {
    }
}
