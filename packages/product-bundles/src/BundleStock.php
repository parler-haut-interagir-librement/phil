<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

/**
 * How many bundles can be sold now, and the component that sets the limit.
 */
final readonly class BundleStock
{
    public function __construct(
        public string $sku,
        public int $quantity,
        public string $limitedBy,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->quantity > 0;
    }
}
