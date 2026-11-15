<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\Money\Money;

/**
 * Gives a unit price other than the sellable's base price: a price list for a customer group, a channel, a period
 * or a quantity tier. The wholesale module implements it; so may an application.
 *
 * Resolvers are asked in the order of their tags, and the first price given wins; when none gives one, the line
 * takes the sellable's base price. A service implementing it is tagged `phil_commerce.unit_price_resolver` by
 * autoconfiguration; it names the resolvers it is asked before or after with the tag's `before` and `after`.
 */
interface UnitPriceResolver
{
    /**
     * The unit price in the sellable's currency, or `null` when this resolver has no price for it in this context.
     */
    public function priceFor(Sellable $sellable, PricingContext $context): ?Money;
}
