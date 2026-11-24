<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\ValueObject;

use Phil\Money\Money;

/**
 * The shop's units of a SKU at a location whose cost is known, and what they are worth at cost.
 */
final readonly class ValuedStock
{
    public function __construct(
        public string $sku,
        public string $location,
        public int $units,
        public Money $value,
    ) {
    }
}
