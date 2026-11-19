<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * An amount owed between this application and a partner for one order: positive when the partner owes it — a
 * reseller's order shipped from here —, negative when it owes the partner — an order forwarded to a supplier. The
 * entry of an order keeps its lines, each SKU with its quantity and unit cost; a return credited against the order is
 * an entry of its own, of the opposite sign, under the return's number.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_syndication_ledger')]
#[ORM\UniqueConstraint(columns: ['partner', 'order_number', 'return_number'])]
final class LedgerEntry
{
    /** The return an entry credits; none for the entry of the order itself. */
    public ?string $returnNumber {
        get => '' === $this->returnKey ? null : $this->returnKey;
    }

    public Money $amount {
        get => Money::of($this->amountMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'settled_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $settledAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'partner', length: 63)]
        public readonly string $partner,
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'recorded_at', type: 'datetime_utc_immutable')]
        public readonly DateTimeImmutable $recordedAt,
        #[ORM\Column(name: 'return_number', length: 64)]
        private readonly string $returnKey,
        /** @var list<array{sku: string, quantity: int, unit_cost: int}> */
        #[ORM\Column(name: 'line_items', type: 'json')]
        public readonly array $lines,
    ) {
    }

    /**
     * @param Money                                                   $amount positive when the partner owes it, negative when it is owed to the partner
     * @param list<array{sku: string, quantity: int, unit_cost: int}> $lines  each SKU, its quantity and unit cost in minor units
     */
    public static function of(Partner $partner, string $orderNumber, Money $amount, DateTimeImmutable $at, array $lines = []): self
    {
        return new self(Uuid::v7()->toRfc4122(), $partner->code, $orderNumber, $amount->minorUnits, $amount->currency->code, $at, '', $lines);
    }

    /**
     * A return credited against an order: the amount of the returned lines, of the sign opposite to the order's.
     *
     * @param list<array{sku: string, quantity: int, unit_cost: int}> $lines the lines returned
     */
    public static function credit(Partner $partner, string $orderNumber, string $returnNumber, Money $amount, DateTimeImmutable $at, array $lines): self
    {
        return new self(Uuid::v7()->toRfc4122(), $partner->code, $orderNumber, $amount->minorUnits, $amount->currency->code, $at, $returnNumber, $lines);
    }

    /**
     * The unit cost of a SKU among the entry's lines; none for a SKU it does not hold.
     */
    public function unitCostOf(string $sku): ?int
    {
        foreach ($this->lines as $line) {
            if ($line['sku'] === $sku) {
                return $line['unit_cost'];
            }
        }

        return null;
    }

    /**
     * The units of a SKU among the entry's lines.
     */
    public function quantityOf(string $sku): int
    {
        $quantity = 0;

        foreach ($this->lines as $line) {
            if ($line['sku'] === $sku) {
                $quantity += $line['quantity'];
            }
        }

        return $quantity;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function settle(DateTimeImmutable $at): void
    {
        $this->settledAt ??= $at;
    }
}
