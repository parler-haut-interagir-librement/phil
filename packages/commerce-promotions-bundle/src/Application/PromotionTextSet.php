<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application;

use Phil\CommercePromotionsBundle\Domain\Model\Promotion;

/**
 * The labels `PromotionTexts` read for a list of promotions, in the locale asked, or each promotion's own.
 */
final readonly class PromotionTextSet
{
    /**
     * @param array<string, string> $labels by promotion code
     */
    public function __construct(
        private array $labels = [],
    ) {
    }

    public function label(Promotion $promotion): string
    {
        return $this->labels[$promotion->code] ?? $promotion->label();
    }

    /**
     * Whether the promotion is shown in a translation rather than in its own text.
     */
    public function isTranslated(Promotion $promotion): bool
    {
        return isset($this->labels[$promotion->code]);
    }
}
