<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostEntryKind;
use Phil\CommerceLogisticsBundle\Domain\Cost\VarianceReason;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * One line of the cost journal: the units of a SKU that moved at a location, whose they were, and the value of the
 * shop's that moved with them. Never changed, never deleted: the stock's value at any moment is the sum of the shop's
 * entries up to it, and the cost of a sale is the sum of its issues less its returns.
 *
 * Units and value carry the movement's sign: positive in, negative out. `uncosted` counts, with the same sign, the
 * units among them whose cost is unknown — received without a unit cost, or before costing began —, which move no
 * value. An entry of an owner other than the shop moves units only.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_logistics_cost_entry')]
#[ORM\Index(columns: ['sku', 'location', 'moved_at'])]
#[ORM\Index(columns: ['reference'])]
#[ORM\Index(columns: ['moved_at'])]
final class CostEntry
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'location', length: 64)]
        public readonly string $location,
        #[ORM\Column(name: 'kind', length: 16, enumType: CostEntryKind::class)]
        public readonly CostEntryKind $kind,
        #[ORM\Column(name: 'owner', length: 64, nullable: true)]
        public readonly ?string $owner,
        #[ORM\Column(name: 'units', type: 'integer')]
        public readonly int $units,
        #[ORM\Column(name: 'uncosted', type: 'integer')]
        public readonly int $uncosted,
        #[ORM\Column(name: 'value', type: 'bigint')]
        private readonly int $value,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currency,
        #[ORM\Column(name: 'reason', length: 16, nullable: true, enumType: VarianceReason::class)]
        public readonly ?VarianceReason $reason,
        #[ORM\Column(name: 'reference', length: 64, nullable: true)]
        public readonly ?string $reference,
        #[ORM\Column(name: 'moved_at')]
        public readonly DateTimeImmutable $movedAt,
    ) {
    }

    /**
     * A movement of the shop's units.
     *
     * @param int $uncosted the units among `$units` whose cost is unknown, with the same sign
     */
    public static function shop(string $sku, string $location, CostEntryKind $kind, int $units, int $uncosted, Money $value, ?string $reference, DateTimeImmutable $at, ?VarianceReason $reason = null): self
    {
        return new self(Uuid::v7()->toRfc4122(), $sku, $location, $kind, null, $units, $uncosted, $value->minorUnits, $value->currency->code, $reason, $reference, $at->setTimezone(new DateTimeZone('UTC')));
    }

    /**
     * A movement of an owner's units, which moves no value of the shop's.
     */
    public static function owned(string $sku, string $location, CostEntryKind $kind, string $owner, int $units, Currency $currency, ?string $reference, DateTimeImmutable $at, ?VarianceReason $reason = null): self
    {
        return new self(Uuid::v7()->toRfc4122(), $sku, $location, $kind, $owner, $units, 0, 0, $currency->code, $reason, $reference, $at->setTimezone(new DateTimeZone('UTC')));
    }

    /**
     * The value of the shop's that moved; zero for an owner's units.
     */
    public function value(): Money
    {
        return Money::of($this->value, Currency::of($this->currency));
    }

    /**
     * The units whose cost is known, with the movement's sign.
     */
    public function costedUnits(): int
    {
        return $this->units - $this->uncosted;
    }
}
