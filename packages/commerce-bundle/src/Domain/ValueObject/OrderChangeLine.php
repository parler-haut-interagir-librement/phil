<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One line of a change to a placed order: the quantity a line of the order ends with — zero removes it —, or a new
 * line of a sellable without options.
 */
final readonly class OrderChangeLine
{
    private function __construct(
        public SellableReference $reference,
        public int $quantity,
    ) {
    }

    public static function of(SellableReference $reference, int $quantity): self
    {
        if ($quantity < 0 || $quantity > 9_999) {
            throw InvalidValue::because(\sprintf('A change sets %s to 0 to 9999 units.', $reference->value()));
        }

        return new self($reference, $quantity);
    }
}
