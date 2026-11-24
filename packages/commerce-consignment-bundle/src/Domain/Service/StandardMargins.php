<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Service;

use Override;
use Phil\CommerceConsignmentBundle\Domain\Contract\MarginCalculator;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignmentTerms;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Margin;
use Phil\Money\Money;

/**
 * The margin of the shop's own units and the commission of consigned ones, in whole minor units.
 *
 * A commission rate splits the price of all the units at once with `Money::allocate()`, which never loses nor creates
 * a cent: the shop's commission and the consignor's share always add up to the price paid. A fixed share is owed
 * whatever the price: a unit sold below it gives a negative commission, which the ledger records as it is.
 */
final readonly class StandardMargins implements MarginCalculator
{
    #[Override]
    public function owned(Money $unitPrice, int $quantity, Money $unitCost): Margin
    {
        return Margin::of($unitPrice->multiply($quantity), $unitCost->multiply($quantity));
    }

    #[Override]
    public function consigned(Money $unitPrice, int $quantity, ConsignmentTerms $terms): Margin
    {
        $revenue = $unitPrice->multiply($quantity);

        if ($terms->share instanceof Money) {
            return Margin::of($revenue, $terms->share->multiply($quantity));
        }

        $commission = $terms->commissionPpm ?? 0;
        [, $share] = $revenue->allocate($commission, ConsignmentTerms::WHOLE - $commission);

        return Margin::of($revenue, $share);
    }
}
