<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Contract;

use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\Money\Money;

/**
 * Works out what units sold brought in and what the shop kept. `Domain\Service\StandardMargins` is the default; an
 * application replaces it — through `phil/pricing`, say — by aliasing this interface to its own service.
 *
 * The same units at the same price always give the same margin: a return is the sale's margin negated.
 */
interface MarginCalculator
{
    /**
     * The shop's own units: the price paid less their cost.
     */
    public function owned(Money $unitPrice, int $quantity, Money $unitCost): Margin;

    /**
     * Units on consignment: the price paid less the consignor's share — the shop's commission.
     */
    public function consigned(Money $unitPrice, int $quantity, ConsignmentTerms $terms): Margin;
}
