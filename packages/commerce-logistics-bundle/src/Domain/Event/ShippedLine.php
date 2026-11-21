<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Event;

/**
 * Units of one SKU that left one location with an order: a line of `UnitsShipped`.
 */
final readonly class ShippedLine
{
    public function __construct(
        public string $sku,
        public string $location,
        public int $units,
    ) {
    }
}
