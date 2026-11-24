<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;

/**
 * One quantity tier of a wholesale price list: from how many units, the unit price, and the markup on the cost
 * that gave it, or null when the price was given.
 */
final readonly class WholesaleTier
{
    public function __construct(
        public int $minimumQuantity,
        public Money $unitPrice,
        public ?Rate $markup,
    ) {
    }
}
