<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * What a shipment really cost the shop: the carrier, its service, the label's tracking number, and the amount the
 * carrier charges, kept beside the order so that it reads against the delivery price the buyer paid. One per
 * shipment.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_shipping_cost')]
#[ORM\Index(columns: ['order_number'])]
final class ShippingCost
{
    public Money $cost {
        get => Money::of($this->costMinorUnits, Currency::of($this->currencyCode));
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'shipment', length: 36, options: ['fixed' => true])]
        public readonly string $shipment,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'carrier', length: 64)]
        public readonly string $carrier,
        #[ORM\Column(name: 'service', length: 32)]
        public readonly string $service,
        #[ORM\Column(name: 'tracking', length: 128, nullable: true)]
        public readonly ?string $tracking,
        #[ORM\Column(name: 'cost', type: 'bigint')]
        private readonly int $costMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'recorded_at')]
        public readonly DateTimeImmutable $recordedAt,
    ) {
    }

    public static function of(Shipment $shipment, string $carrier, string $service, Money $cost, DateTimeImmutable $at, ?string $tracking = null): self
    {
        if (1 !== preg_match('/^[\x20-\x7E]{1,64}$/D', $carrier)) {
            throw InvalidValue::because('A carrier is named in 1 to 64 printable ASCII characters.');
        }

        if (1 !== preg_match('/^[\x21-\x7E]{1,32}$/D', $service)) {
            throw InvalidValue::because(\sprintf('"%s" is not a service code: 1 to 32 visible ASCII characters.', $service));
        }

        if (null !== $tracking && 1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $tracking)) {
            throw InvalidValue::because('A tracking number is 1 to 128 visible ASCII characters.');
        }

        if ($cost->isNegative()) {
            throw InvalidValue::because(\sprintf('The parcel of the shipment %s costs zero or more.', $shipment->id));
        }

        return new self($shipment->id, $shipment->orderNumber, $carrier, $service, $tracking, $cost->minorUnits, $cost->currency->code, $at);
    }
}
