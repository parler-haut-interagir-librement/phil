<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Document;

use DateTimeImmutable;

/**
 * What a picking slip prints for an order: its number from the core's register, the units to take location by
 * location, and whether to wrap the order, and in which wrap.
 */
final readonly class PickingSlip
{
    /**
     * @param list<PickingLine> $lines by location, then by SKU; the lines without a location last
     */
    public function __construct(
        public string $number,
        public string $orderNumber,
        public DateTimeImmutable $issuedAt,
        public array $lines,
        public bool $giftWrapped,
        public bool $recycledWrap,
    ) {
    }
}
