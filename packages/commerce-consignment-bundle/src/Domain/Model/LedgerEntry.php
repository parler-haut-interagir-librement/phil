<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * One line of the ownership ledger: units of a layer that moved, why, when, under which reference, and for a sale or a
 * return what they brought in, what they cost or what the consignor is owed, and the margin. Never changed, never
 * deleted: the ledger tells how each owner's stock came to be, and what each consignor is owed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_consignment_entry')]
#[ORM\Index(name: 'phil_commerce_consignment_entry_reference', columns: ['reference', 'sku'])]
#[ORM\Index(name: 'phil_commerce_consignment_entry_place', columns: ['sku', 'location', 'moved_at'])]
#[ORM\Index(name: 'phil_commerce_consignment_entry_consignor', columns: ['consignor', 'moved_at'])]
final class LedgerEntry
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'layer', length: 36, options: ['fixed' => true])]
        public readonly string $layer,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
        #[ORM\Column(name: 'consignor', length: 64)]
        private readonly string $consignor,
        #[ORM\Column(name: 'kind', length: 16, enumType: EntryKind::class)]
        public readonly EntryKind $kind,
        #[ORM\Column(name: 'quantity', type: 'integer')]
        public readonly int $quantity,
        #[ORM\Column(name: 'reference', length: 64, nullable: true)]
        public readonly ?string $reference,
        #[ORM\Column(name: 'moved_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $movedAt,
        #[ORM\Column(name: 'currency', length: 3, nullable: true, options: ['fixed' => true])]
        private readonly ?string $currency = null,
        #[ORM\Column(name: 'unit_price', type: 'bigint', nullable: true)]
        private readonly ?int $unitPrice = null,
        #[ORM\Column(name: 'revenue', type: 'bigint', nullable: true)]
        private readonly ?int $revenue = null,
        #[ORM\Column(name: 'cost', type: 'bigint', nullable: true)]
        private readonly ?int $cost = null,
    ) {
    }

    /**
     * A movement without money: received, adopted, transferred or counted.
     *
     * @param int $quantity the units the layer gained, or lost when negative
     */
    public static function movement(StockLayer $layer, EntryKind $kind, int $quantity, ?string $reference, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $layer->id, $layer->sku, $layer->location, $layer->owner()->key(), $kind, $quantity, $reference, $at->setTimezone(new DateTimeZone('UTC')));
    }

    /**
     * Units sold, a negative quantity, or returned, a positive one, with what they brought in and cost: a return's margin
     * is negative.
     */
    public static function sale(StockLayer $layer, EntryKind $kind, int $quantity, string $reference, Money $unitPrice, Margin $margin, DateTimeImmutable $at): self
    {
        return new self(
            Uuid::v7()->toRfc4122(),
            $layer->id,
            $layer->sku,
            $layer->location,
            $layer->owner()->key(),
            $kind,
            $quantity,
            $reference,
            $at->setTimezone(new DateTimeZone('UTC')),
            $unitPrice->currency->code,
            $unitPrice->minorUnits,
            $margin->revenue->minorUnits,
            $margin->cost->minorUnits,
        );
    }

    public function owner(): Owner
    {
        return Owner::fromKey($this->consignor);
    }

    public function movedAt(): DateTimeImmutable
    {
        return $this->movedAt;
    }

    /**
     * The price of one unit, for a sale or a return.
     */
    public function unitPrice(): ?Money
    {
        return null === $this->unitPrice || null === $this->currency ? null : Money::of($this->unitPrice, Currency::of($this->currency));
    }

    /**
     * What the units brought in and cost — or owe the consignor —, for a sale or a return; null for any other movement.
     */
    public function margin(): ?Margin
    {
        if (null === $this->revenue || null === $this->cost || null === $this->currency) {
            return null;
        }

        $currency = Currency::of($this->currency);

        return Margin::of(Money::of($this->revenue, $currency), Money::of($this->cost, $currency));
    }
}
