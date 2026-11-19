<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Contract;

use Phil\CommerceBundle\Domain\ValueObject\Adjustment;

/**
 * Tells which promotion adjustments come from a special — a price for products bought together —, whose discount
 * disappears when one of its products comes back. `Infrastructure\Promotions\PromotionSpecials` reads the bundle
 * prices of `phil/commerce-promotions-bundle`.
 */
interface SpecialDiscounts
{
    public function isSpecial(Adjustment $adjustment): bool;
}
