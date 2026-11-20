<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Carrier;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a carrier is asked to produce a label for: a parcel between two complete addresses, by one of its services,
 * with the shop's reference printed on it — the order number, as a rule.
 */
final readonly class LabelRequest
{
    private function __construct(
        public ShippingAddress $from,
        public ShippingAddress $to,
        public Parcel $parcel,
        public string $service,
        public string $reference,
    ) {
    }

    public static function of(ShippingAddress $from, ShippingAddress $to, Parcel $parcel, string $service, string $reference = ''): self
    {
        if (!$from->isComplete() || !$to->isComplete()) {
            throw InvalidValue::because('A label goes from a complete address to a complete address: a name, a street, a city and a postal code.');
        }

        if (1 !== preg_match('/^[\x21-\x7E]{1,32}$/D', $service)) {
            throw InvalidValue::because(\sprintf('"%s" is not a service code: 1 to 32 visible ASCII characters.', $service));
        }

        if (1 !== preg_match('/^[\x20-\x7E]{0,35}$/D', $reference)) {
            throw InvalidValue::because('A reference printed on a label is up to 35 printable ASCII characters.');
        }

        return new self($from, $to, $parcel, $service, $reference);
    }
}
