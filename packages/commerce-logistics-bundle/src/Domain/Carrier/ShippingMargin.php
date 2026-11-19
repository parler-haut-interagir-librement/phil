<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

use Phil\Money\Money;

/**
 * An order's delivery, what the buyer was charged against what its parcels cost the shop.
 */
final readonly class ShippingMargin
{
    public function __construct(
        public Money $charged,
        public Money $cost,
    ) {
    }

    /**
     * What the shop keeps of the delivery charge: negative when the parcels cost more than the buyer paid.
     */
    public function margin(): Money
    {
        return $this->charged->subtract($this->cost);
    }
}
