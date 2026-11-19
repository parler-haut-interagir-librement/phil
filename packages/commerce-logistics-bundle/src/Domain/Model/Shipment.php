<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * A parcel that left for an order: how many units of which lines, the carrier and its tracking number. An order may
 * leave in several.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_shipment')]
#[ORM\Index(columns: ['order_number'])]
final class Shipment
{
    /**
     * @param array<string, int> $lines units by order line, as `SellableReference::value()` gives it: `type:id`, or
     *                                  `type.fingerprint:id` for a configured line
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'line_items', type: 'json')]
        public readonly array $lines,
        #[ORM\Column(name: 'shipped_at')]
        public readonly DateTimeImmutable $shippedAt,
        #[ORM\Column(name: 'carrier', length: 64, nullable: true)]
        public readonly ?string $carrier,
        #[ORM\Column(name: 'tracking', length: 128, nullable: true)]
        public readonly ?string $tracking,
    ) {
    }

    /**
     * @param array<string, int> $lines units by order line, each one or more
     */
    public static function of(string $orderNumber, array $lines, DateTimeImmutable $at, ?string $carrier = null, ?string $tracking = null): self
    {
        if ([] === $lines) {
            throw InvalidValue::because(\sprintf('A shipment of %s holds at least one line.', $orderNumber));
        }

        foreach ($lines as $line => $quantity) {
            if ($quantity < 1) {
                throw InvalidValue::because(\sprintf('A shipment holds one unit of %s or more; %d is refused.', $line, $quantity));
            }
        }

        if (null !== $carrier && 1 !== preg_match('/^[\x20-\x7E]{1,64}$/D', $carrier)) {
            throw InvalidValue::because('A carrier is named in 1 to 64 printable ASCII characters.');
        }

        if (null !== $tracking && 1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $tracking)) {
            throw InvalidValue::because('A tracking number is 1 to 128 visible ASCII characters.');
        }

        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $lines, $at, $carrier, $tracking);
    }

    public function quantityOf(string $line): int
    {
        return $this->lines[$line] ?? 0;
    }
}
