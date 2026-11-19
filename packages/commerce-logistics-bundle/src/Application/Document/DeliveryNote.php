<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Document;

use DateTimeImmutable;

/**
 * What a delivery note prints for one shipment: its number from the core's register, the lines in the parcel, the
 * carrier and tracking number, and the gift options — the wrapping, the message, the prices left off.
 */
final readonly class DeliveryNote
{
    /**
     * @param list<DeliveryNoteLine> $lines
     */
    public function __construct(
        public string $number,
        public string $orderNumber,
        public string $shipment,
        public DateTimeImmutable $issuedAt,
        public array $lines,
        public ?string $carrier,
        public ?string $tracking,
        public bool $giftWrapped,
        public bool $recycledWrap,
        public ?string $giftMessage,
        public bool $pricesHidden,
    ) {
    }
}
