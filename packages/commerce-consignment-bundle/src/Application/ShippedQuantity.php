<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

/**
 * Units of a SKU an order took off the shelves at one location.
 */
final readonly class ShippedQuantity
{
    public function __construct(
        public string $sku,
        public string $location,
        public int $quantity,
    ) {
    }
}
