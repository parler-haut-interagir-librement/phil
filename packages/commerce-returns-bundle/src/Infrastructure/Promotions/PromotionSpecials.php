<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\Promotions;

use Override;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Promotion\PromotionAction;
use Phil\CommerceReturnsBundle\Application\Contract\SpecialDiscounts;

/**
 * `SpecialDiscounts` on `phil/commerce-promotions-bundle`: a special is a bundle price, a price for each complete
 * bundle of products, read from the promotion its adjustment's origin names. A promotion deleted since is no special.
 */
final readonly class PromotionSpecials implements SpecialDiscounts
{
    public function __construct(
        private PromotionRepository $promotions,
    ) {
    }

    #[Override]
    public function isSpecial(Adjustment $adjustment): bool
    {
        $origin = PromotionEvaluator::originOf($adjustment);
        $promotion = null === $origin ? null : $this->promotions->find($origin['promotion']);

        return $promotion instanceof Promotion && PromotionAction::BundlePrice === $promotion->action;
    }
}
