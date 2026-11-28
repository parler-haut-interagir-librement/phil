<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Shipping;

use Phil\Address\Address;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * What a carrier needs to ship one deliverable: a reference of the application's, the recipient's address, the code of
 * the benefit shipped, and a telephone number when the carrier asks for one.
 */
final readonly class ShipmentRequest
{
    /**
     * @param string      $reference the application's reference of the shipment, 1 to 64 characters, which names
     *                               neither the member nor their tier
     * @param string|null $phone     E.164, when the carrier asks for one
     */
    public function __construct(
        public string $reference,
        public Address $recipient,
        public string $benefit,
        public ?string $phone = null,
    ) {
        if ('' === trim($reference) || \strlen($reference) > 64) {
            throw InvalidDeliverable::because('A shipment is referenced by 1 to 64 characters.');
        }

        if (null !== $phone && 1 !== preg_match('/^\+[1-9]\d{6,14}$/D', $phone)) {
            throw InvalidDeliverable::because('A telephone number is written as E.164, such as +15145550123.');
        }
    }
}
