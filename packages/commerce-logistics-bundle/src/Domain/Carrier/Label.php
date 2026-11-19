<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

use Phil\Money\Money;

/**
 * A label a carrier produced: its tracking number, what the carrier charges the shop for it, and the document to
 * print — its bytes and their media type, `application/pdf` as a rule.
 */
final readonly class Label
{
    public function __construct(
        public string $carrier,
        public string $service,
        public string $tracking,
        public Money $cost,
        public string $document,
        public string $mediaType,
    ) {
    }
}
