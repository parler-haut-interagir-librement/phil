<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;

/**
 * One discount of a cascade: its rate, the price it applied to, the amount it took off and the price it left.
 */
final readonly class DiscountStep
{
    public function __construct(
        public Rate $rate,
        public Money $before,
        public Money $discount,
        public Money $after,
    ) {
    }
}
