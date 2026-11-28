<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

/**
 * Units of a component to reserve, issue or put back for a sale or a return of bundles.
 */
final readonly class StockDraw
{
    public function __construct(
        public string $sku,
        public int $units,
    ) {
    }
}
