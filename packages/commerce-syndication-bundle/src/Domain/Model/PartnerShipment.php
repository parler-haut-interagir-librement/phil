<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A parcel a supplier sent for an order a reseller forwarded: its id at the supplier, the units of each SKU, the
 * carrier and the tracking number. The supplier keeps it with the reseller as partner, the reseller with the supplier;
 * neither keeps where it went.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_shipment')]
#[ORM\Index(columns: ['order_number'])]
final class PartnerShipment
{
    /** The most SKUs one shipment lists. */
    public const int MAX_LINES = 500;

    /**
     * @param array<int|string, int> $lines SKU → units; a numeric SKU is an integer key, as PHP makes it
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'partner', length: 63)]
        public readonly string $partner,
        #[ORM\Id]
        #[ORM\Column(name: 'shipment', length: 64)]
        public readonly string $shipment,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'line_items', type: 'json')]
        public readonly array $lines,
        #[ORM\Column(name: 'carrier', length: 64, nullable: true)]
        public readonly ?string $carrier,
        #[ORM\Column(name: 'tracking', length: 128, nullable: true)]
        public readonly ?string $tracking,
        #[ORM\Column(name: 'shipped_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $shippedAt,
    ) {
    }

    /**
     * @param array<int|string, int> $lines SKU → units, each one or more
     */
    public static function of(Partner $partner, string $shipment, string $orderNumber, array $lines, DateTimeImmutable $at, ?string $carrier = null, ?string $tracking = null): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $shipment) || 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $orderNumber)) {
            throw InvalidValue::because('A shipment and its order are each named in 1 to 64 visible ASCII characters.');
        }

        self::checkLines($lines);

        if (null !== $carrier && 1 !== preg_match('/^[\x20-\x7E]{1,64}$/D', $carrier)) {
            throw InvalidValue::because('A carrier is named in 1 to 64 printable ASCII characters.');
        }

        if (null !== $tracking && 1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $tracking)) {
            throw InvalidValue::because('A tracking number is 1 to 128 visible ASCII characters.');
        }

        return new self($partner->code, $shipment, $orderNumber, $lines, $carrier, $tracking, $at);
    }

    /**
     * @param array<int|string, int> $lines
     */
    public static function checkLines(array $lines): void
    {
        if ([] === $lines || \count($lines) > self::MAX_LINES) {
            throw InvalidValue::because(\sprintf('A shipment or a return lists 1 to %d SKUs.', self::MAX_LINES));
        }

        foreach ($lines as $sku => $quantity) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', (string) $sku) || $quantity < 1) {
                throw InvalidValue::because('A shipment or a return holds one unit or more of each SKU, each 1 to 64 visible ASCII characters.');
            }
        }
    }
}
