<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;

/**
 * What one bundle costs the shop: the sum of its components' costs, or null with the components whose cost is unknown.
 */
final readonly class BundleCost
{
    /**
     * @param list<ComponentCost> $components in the bundle's order
     * @param list<string>        $unknown    the SKUs whose cost is unknown
     */
    public function __construct(
        public string $sku,
        public ?Money $unitCost,
        public array $components,
        public array $unknown,
    ) {
    }

    public function isKnown(): bool
    {
        return $this->unitCost instanceof Money;
    }
}
