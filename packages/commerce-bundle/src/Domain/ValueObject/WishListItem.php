<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One thing on a wish list: what is wished for, how many, and how many others already bought.
 */
final readonly class WishListItem
{
    private function __construct(
        public SellableReference $reference,
        public int $wanted,
        public int $bought,
    ) {
    }

    public static function of(SellableReference $reference, int $wanted, int $bought = 0): self
    {
        if ($wanted < 1 || $wanted > 9_999 || $bought < 0 || $bought > $wanted) {
            throw InvalidValue::because(\sprintf('A wish for %s is of 1 to 9999 units, of which 0 to all are bought.', $reference->value()));
        }

        return new self($reference, $wanted, $bought);
    }

    /**
     * How many are still wished for.
     */
    public function remaining(): int
    {
        return $this->wanted - $this->bought;
    }
}
