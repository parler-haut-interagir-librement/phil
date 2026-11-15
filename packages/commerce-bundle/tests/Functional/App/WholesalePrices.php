<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\Money\Money;

/**
 * A price list as the wholesale module would give one: a quarter off for the `wholesale` group.
 */
final readonly class WholesalePrices implements UnitPriceResolver
{
    #[Override]
    public function priceFor(Sellable $sellable, PricingContext $context): ?Money
    {
        if ('wholesale' !== $context->customerGroup) {
            return null;
        }

        return Money::of(intdiv($sellable->unitPrice->minorUnits * 3, 4), $sellable->unitPrice->currency);
    }
}
