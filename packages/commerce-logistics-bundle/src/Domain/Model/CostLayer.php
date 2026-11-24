<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Exception\CostRefused;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Units of one SKU at one location that came in together, and what they are worth: a receipt under first in, first
 * out, or the pool of a weighted average. A layer of the shop holds a value; a layer of an owner — units left on
 * consignment — holds units only, never part of the shop's stock value. A layer that is emptied is removed: the cost
 * journal keeps what it held.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_cost_layer')]
#[ORM\Index(columns: ['sku', 'location'])]
final class CostLayer
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
        #[ORM\Column(name: 'owner', length: 64, nullable: true)]
        public readonly ?string $owner,
        #[ORM\Column(name: 'units', type: 'integer')]
        private int $units,
        #[ORM\Column(name: 'value', type: 'bigint')]
        private int $value,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private string $currency,
        #[ORM\Column(name: 'received_at')]
        public readonly DateTimeImmutable $receivedAt,
        #[ORM\Column(name: 'reference', length: 64, nullable: true)]
        public readonly ?string $reference,
    ) {
    }

    /**
     * Units of the shop, worth this value in all.
     */
    public static function shop(string $sku, string $location, int $units, Money $value, DateTimeImmutable $receivedAt, ?string $reference): self
    {
        if ($units < 1 || $value->isNegative()) {
            throw CostRefused::layer($sku, $location, $units, $value);
        }

        return new self(Uuid::v7()->toRfc4122(), $sku, $location, null, $units, $value->minorUnits, $value->currency->code, $receivedAt->setTimezone(new DateTimeZone('UTC')), $reference);
    }

    /**
     * Units of an owner other than the shop: they carry no value of the shop's.
     */
    public static function owned(string $sku, string $location, string $owner, int $units, Currency $currency, DateTimeImmutable $receivedAt, ?string $reference): self
    {
        if ($units < 1) {
            throw CostRefused::layer($sku, $location, $units, Money::zero($currency));
        }

        return new self(Uuid::v7()->toRfc4122(), $sku, $location, $owner, $units, 0, $currency->code, $receivedAt->setTimezone(new DateTimeZone('UTC')), $reference);
    }

    public function units(): int
    {
        return $this->units;
    }

    public function value(): Money
    {
        return Money::of($this->value, Currency::of($this->currency));
    }

    public function isShops(): bool
    {
        return null === $this->owner;
    }

    /**
     * Takes units out and gives the value they leave with: their share of the layer's value, the last unit taking
     * whatever is left, so the values taken add up to the layer's to the minor unit.
     */
    public function take(int $units): Money
    {
        if ($units < 1 || $units > $this->units) {
            throw CostRefused::overTaken($this->sku, $this->location, $units, $this->units);
        }

        $taken = $this->value()->allocate($units, $this->units - $units)[0];
        $this->units -= $units;
        $this->value -= $taken->minorUnits;

        return $taken;
    }

    /**
     * Adds units worth this value in all: a receipt into a weighted average, a customer return.
     */
    public function add(int $units, Money $value): void
    {
        if ($units < 1) {
            throw CostRefused::layer($this->sku, $this->location, $units, $value);
        }

        $this->addValue($value);
        $this->units += $units;
    }

    /**
     * Adds value to the units already there: the share of a landed cost.
     */
    public function addValue(Money $value): void
    {
        if ($value->isNegative()) {
            throw CostRefused::negative($this->sku, $value);
        }

        if (!$value->currency->equals(Currency::of($this->currency))) {
            throw CostRefused::currency($this->sku, $this->currency, $value->currency->code);
        }

        if (!$this->isShops() && !$value->isZero()) {
            throw CostRefused::ownedValue($this->sku, $this->location);
        }

        $this->value = $this->value()->add($value)->minorUnits;
    }
}
