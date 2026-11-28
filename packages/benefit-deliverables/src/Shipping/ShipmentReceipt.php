<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Shipping;

use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * What a carrier answers for a shipment it took: its name, and the tracking number of the parcel.
 */
final readonly class ShipmentReceipt
{
    public function __construct(
        public string $carrier,
        public string $trackingNumber,
    ) {
        if ('' === trim($carrier) || \strlen($carrier) > 64 || '' === trim($trackingNumber) || \strlen($trackingNumber) > 64) {
            throw InvalidDeliverable::because('A receipt names its carrier and its tracking number, 1 to 64 characters each.');
        }
    }
}
