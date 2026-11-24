<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\Money\Money;

/**
 * Units of a SKU sold, or brought back, under one reference — an order number, a receipt —, line by line of the
 * layers they came from or went back to.
 */
final readonly class Sale
{
    /**
     * @param list<SaleLine> $lines
     */
    public function __construct(
        public string $reference,
        public string $sku,
        public array $lines,
    ) {
    }

    public function quantity(): int
    {
        return array_sum(array_map(static fn (SaleLine $line): int => $line->quantity, $this->lines));
    }

    /**
     * The units of one owner in the sale.
     */
    public function quantityOf(Owner $owner): int
    {
        $quantity = 0;

        foreach ($this->lines as $line) {
            if ($line->owner->equals($owner)) {
                $quantity += $line->quantity;
            }
        }

        return $quantity;
    }

    /**
     * The margin over every line; null for a sale of no unit.
     */
    public function margin(): ?Margin
    {
        $total = null;

        foreach ($this->lines as $line) {
            $total = $total?->add($line->margin) ?? $line->margin;
        }

        return $total;
    }

    /**
     * What the shop owes each consignor for the sale, by consignor.
     *
     * @return array<string, Money>
     */
    public function owedToConsignors(): array
    {
        $owed = [];

        foreach ($this->lines as $line) {
            $consignor = $line->owner->consignor;

            if (null !== $consignor) {
                $owed[$consignor] = isset($owed[$consignor]) ? $owed[$consignor]->add($line->margin->cost) : $line->margin->cost;
            }
        }

        return $owed;
    }
}
