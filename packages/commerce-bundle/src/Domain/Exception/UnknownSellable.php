<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * No `SellableResolver` supports the reference's type, or the one that does knows nothing by that identifier.
 */
final class UnknownSellable extends DomainException implements DomainError
{
    public static function of(SellableReference $reference): self
    {
        return new self(\sprintf('Nothing sellable is known as %s.', $reference->value()));
    }
}
