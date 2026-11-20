<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Document;

use Phil\Money\Money;

/**
 * One line of a delivery note: what the parcel holds of an order line.
 */
final readonly class DeliveryNoteLine
{
    /**
     * @param string     $line      the order line, as `SellableReference::value()` gives it
     * @param Money|null $unitPrice none when the order's gift options hide the prices
     */
    public function __construct(
        public string $line,
        public string $description,
        public int $quantity,
        public ?Money $unitPrice,
    ) {
    }
}
