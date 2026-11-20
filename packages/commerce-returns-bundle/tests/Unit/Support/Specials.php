<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\Contract\SetParts;
use Phil\CommerceReturnsBundle\Application\Contract\SpecialDiscounts;

/**
 * Specials named by their origins, and sets by their lines.
 */
final readonly class Specials implements SpecialDiscounts, SetParts
{
    /**
     * @param list<string>                $origins
     * @param array<string, list<string>> $sets    line → parts
     */
    public function __construct(
        private array $origins = [],
        private array $sets = [],
    ) {
    }

    #[Override]
    public function isSpecial(Adjustment $adjustment): bool
    {
        return \in_array($adjustment->origin, $this->origins, true);
    }

    #[Override]
    public function partsOf(SellableReference $reference): array
    {
        return $this->sets[$reference->value()] ?? [];
    }
}
