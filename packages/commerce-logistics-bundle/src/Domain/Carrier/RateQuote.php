<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

use Phil\Money\Money;

/**
 * What a carrier would charge the shop for a parcel by one of its services, and how many business days it expects
 * the parcel to travel. It is the shop's cost: the price the buyer pays for delivery is the delivery method's.
 */
final readonly class RateQuote
{
    public function __construct(
        public string $carrier,
        public string $service,
        public string $serviceName,
        public Money $cost,
        public ?int $transitDays = null,
    ) {
    }
}
