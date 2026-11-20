<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Symfony\Component\Uid\Uuid;

/**
 * One line of the stock journal: what moved, where, why, when, and what it was for. Never changed, never deleted:
 * the journal tells how every stock level came to be.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_stock_movement')]
#[ORM\Index(columns: ['sku', 'location', 'moved_at'])]
#[ORM\Index(columns: ['reference'])]
final class StockMovement
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public string $id,
        #[ORM\Column(name: 'sku', length: 64)]
        public string $sku,
        #[ORM\Column(name: 'location', length: 64)]
        public string $location,
        #[ORM\Column(name: 'kind', length: 16, enumType: MovementKind::class)]
        public MovementKind $kind,
        #[ORM\Column(name: 'on_hand_change', type: 'integer')]
        public int $onHandChange,
        #[ORM\Column(name: 'reserved_change', type: 'integer')]
        public int $reservedChange,
        #[ORM\Column(name: 'reference', length: 64, nullable: true)]
        public ?string $reference,
        #[ORM\Column(name: 'moved_at')]
        public DateTimeImmutable $movedAt,
    ) {
    }

    /**
     * @param string|null $reference what the movement was for: an order number, a delivery note, a count sheet
     */
    public static function of(StockLevel $level, MovementKind $kind, int $onHandChange, int $reservedChange, ?string $reference, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $level->sku, $level->location, $kind, $onHandChange, $reservedChange, $reference, $at);
    }
}
