<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReceiptLine;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Units of one SKU at one location that one owner holds, all received together on the same terms: the shop's at a unit
 * cost, a consignor's on a share or a commission.
 *
 * A receipt line opens a layer; a transfer, or a return to another location, puts units in a layer of their own that
 * keeps the receipt's instant, cost and terms, and names the receipt's layer as its origin. Sales, transfers
 * and counts take units from a layer, returns put them back; its version guards against two of them writing at once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_consignment_layer')]
#[ORM\Index(name: 'phil_commerce_consignment_layer_place', columns: ['sku', 'location'])]
#[ORM\Index(name: 'phil_commerce_consignment_layer_consignor', columns: ['consignor', 'sku'])]
final class StockLayer
{
    #[ORM\Column(name: 'remaining', type: 'integer')]
    private int $remaining;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'origin', length: 36, options: ['fixed' => true])]
        public readonly string $origin,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
        #[ORM\Column(name: 'consignor', length: 64)]
        private readonly string $consignor,
        #[ORM\Column(name: 'currency', length: 3, nullable: true, options: ['fixed' => true])]
        private readonly ?string $currency,
        #[ORM\Column(name: 'unit_cost', type: 'bigint', nullable: true)]
        private readonly ?int $unitCost,
        #[ORM\Column(name: 'share', type: 'bigint', nullable: true)]
        private readonly ?int $share,
        #[ORM\Column(name: 'commission_ppm', type: 'integer', nullable: true)]
        private readonly ?int $commissionPpm,
        #[ORM\Column(name: 'received_at', type: 'datetime_utc_immutable')]
        private readonly DateTimeImmutable $receivedAt,
        #[ORM\Column(name: 'reference', length: 64, nullable: true)]
        public readonly ?string $reference,
        #[ORM\Column(name: 'received', type: 'integer')]
        public readonly int $received,
    ) {
        $this->remaining = $received;
    }

    public static function receive(ReceiptLine $line, string $location, string $reference, DateTimeImmutable $at): self
    {
        $id = Uuid::v7()->toRfc4122();
        $money = $line->unitCost ?? $line->terms?->share;

        return new self(
            $id,
            $id,
            $line->sku,
            InvalidValue::checkIdentifier($location, 'location'),
            $line->owner->key(),
            $money?->currency->code,
            $line->unitCost?->minorUnits,
            $line->terms?->share?->minorUnits,
            $line->terms?->commissionPpm,
            $at->setTimezone(new DateTimeZone('UTC')),
            $reference,
            $line->quantity,
        );
    }

    /**
     * The shop's own layer for units it bought from a consignor's layer, at the agreed unit cost: a receipt of its own,
     * at the instant of the buy-out, where nothing moved physically.
     */
    public static function boughtFrom(self $layer, int $quantity, Money $unitCost, string $reference, DateTimeImmutable $at): self
    {
        $id = Uuid::v7()->toRfc4122();

        return new self(
            $id,
            $id,
            $layer->sku,
            $layer->location,
            '',
            $unitCost->currency->code,
            $unitCost->minorUnits,
            null,
            null,
            $at->setTimezone(new DateTimeZone('UTC')),
            $reference,
            InvalidValue::checkQuantity($quantity),
        );
    }

    /**
     * A new layer at another location for units that come back to it — a return elsewhere than where they were sold.
     */
    public function copyAt(string $location, int $quantity): self
    {
        return new self(
            Uuid::v7()->toRfc4122(),
            $this->origin,
            $this->sku,
            InvalidValue::checkIdentifier($location, 'location'),
            $this->consignor,
            $this->currency,
            $this->unitCost,
            $this->share,
            $this->commissionPpm,
            $this->receivedAt,
            $this->reference,
            InvalidValue::checkQuantity($quantity),
        );
    }

    public function owner(): Owner
    {
        return Owner::fromKey($this->consignor);
    }

    public function remaining(): int
    {
        return $this->remaining;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function receivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    /**
     * What one unit cost the shop; null for units on consignment.
     */
    public function unitCost(): ?Money
    {
        return null === $this->unitCost || null === $this->currency ? null : Money::of($this->unitCost, Currency::of($this->currency));
    }

    /**
     * The terms agreed with the consignor; null for the shop's own units.
     */
    public function terms(): ?ConsignmentTerms
    {
        if (null !== $this->share && null !== $this->currency) {
            return ConsignmentTerms::share(Money::of($this->share, Currency::of($this->currency)));
        }

        return null === $this->commissionPpm ? null : ConsignmentTerms::commission($this->commissionPpm);
    }

    /**
     * Takes units off the layer: sold, transferred or not found by a count.
     */
    public function take(int $quantity): void
    {
        InvalidValue::checkQuantity($quantity);

        if ($quantity > $this->remaining) {
            throw ConsignmentRefused::shortage($this->sku, $this->location, $quantity, $this->remaining);
        }

        $this->remaining -= $quantity;
    }

    /**
     * Puts units back on the layer: returned, transferred back or found by a count.
     */
    public function restock(int $quantity): void
    {
        $this->remaining += InvalidValue::checkQuantity($quantity);
    }
}
