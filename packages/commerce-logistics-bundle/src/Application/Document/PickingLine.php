<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Document;

/**
 * Units of a SKU to take from one location; no location for a product whose stock is not tracked.
 */
final readonly class PickingLine
{
    public function __construct(
        public string $sku,
        public ?string $location,
        public int $quantity,
    ) {
    }
}
