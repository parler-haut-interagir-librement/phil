<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;

/**
 * A component of a composed cost: its unit cost and what its units of one bundle cost, both null when unknown.
 */
final readonly class ComponentCost
{
    public function __construct(
        public string $sku,
        public int $quantity,
        public ?Money $unitCost,
        public ?Money $cost,
    ) {
    }
}
