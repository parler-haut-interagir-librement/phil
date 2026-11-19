<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

/**
 * What a carrier is asked to price: a parcel from one address to another.
 */
final readonly class RateRequest
{
    public function __construct(
        public ShippingAddress $from,
        public ShippingAddress $to,
        public Parcel $parcel,
    ) {
    }
}
