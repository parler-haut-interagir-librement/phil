<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\Money\Money;

/**
 * What units sold brought in and what the shop kept: for stock it owns, the price paid less its cost; for stock on
 * consignment, its commission — the price paid less the consignor's share, which the shop owes the consignor.
 */
final readonly class Margin
{
    /**
     * @param Money $revenue the price paid for the units
     * @param Money $cost    their cost, for stock the shop owns; the consignor's share, for stock on consignment
     * @param Money $margin  the revenue less the cost: the margin, or the commission
     */
    private function __construct(
        public Money $revenue,
        public Money $cost,
        public Money $margin,
    ) {
    }

    public static function of(Money $revenue, Money $cost): self
    {
        return new self($revenue, $cost, $revenue->subtract($cost));
    }

    public function add(self $other): self
    {
        return self::of($this->revenue->add($other->revenue), $this->cost->add($other->cost));
    }

    public function negate(): self
    {
        return self::of($this->revenue->negate(), $this->cost->negate());
    }
}
