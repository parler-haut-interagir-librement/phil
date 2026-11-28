<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;

/**
 * A component as it was sold: the units one bundle held, its share of one bundle's price, and its unit cost when known.
 */
final readonly class SoldComponent
{
    public function __construct(
        public string $sku,
        public int $perBundle,
        public Money $unitShare,
        public ?Money $unitCost = null,
    ) {
    }
}
