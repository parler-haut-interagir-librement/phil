<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;
use Phil\Pricing\Rate;

/**
 * The margin of one bundle at its price: the price less the composed cost, and its rate over the price.
 */
final readonly class BundleMargin
{
    public function __construct(
        public string $sku,
        public Money $price,
        public Money $cost,
        public Money $margin,
        public ?Rate $rate,
    ) {
    }
}
