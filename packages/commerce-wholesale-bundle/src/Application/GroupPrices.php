<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Repository\CustomerGroupRepository;
use Phil\Money\Money;

/**
 * Gives the core the price of a product for the order's customer group: its base price less the group's share. It is
 * asked after the catalogue's price books, so a group's own price list comes first.
 */
final readonly class GroupPrices implements UnitPriceResolver
{
    public function __construct(
        private CustomerGroupRepository $groups,
    ) {
    }

    #[Override]
    public function priceFor(Sellable $sellable, PricingContext $context): ?Money
    {
        $group = null === $context->customerGroup || 'product' !== $sellable->reference->type ? null : $this->groups->findGroup($context->customerGroup);

        if (!$group instanceof CustomerGroup || !$group->isActive() || 0 === $group->discount() || !$group->currency->equals($sellable->unitPrice->currency)) {
            return null;
        }

        return $group->priceFrom($sellable->unitPrice);
    }
}
