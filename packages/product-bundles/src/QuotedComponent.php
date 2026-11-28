<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;

/**
 * A component of a quote: its unit price on its own, what its units of one bundle sell for apart, and the share of the
 * bundle's price it takes.
 */
final readonly class QuotedComponent
{
    public function __construct(
        public string $sku,
        public int $quantity,
        public Money $unitPrice,
        public Money $value,
        public Money $share,
    ) {
    }
}
