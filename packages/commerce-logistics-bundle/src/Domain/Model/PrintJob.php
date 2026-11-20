<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Units of an order's line sent to be printed on demand rather than taken from stock: a convention was near when the
 * order was placed. Recorded once per order and SKU, so the decision holds however many times the order is reserved.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_print_job')]
#[ORM\UniqueConstraint(columns: ['order_number', 'sku'])]
final class PrintJob
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'quantity', type: 'integer')]
        public readonly int $quantity,
        #[ORM\Column(name: 'sent_at')]
        public readonly DateTimeImmutable $sentAt,
    ) {
    }

    public static function of(string $orderNumber, string $sku, int $quantity, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $orderNumber, $sku, $quantity, $at);
    }
}
