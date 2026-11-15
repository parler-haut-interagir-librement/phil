<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * Something sellable that the order's channel does not sell: outside its selection.
 */
final class NotSoldThere extends DomainException implements DomainError
{
    public static function of(SellableReference $reference, ?string $channel): self
    {
        return new self(\sprintf('%s is not sold through %s.', $reference->value(), $channel ?? 'an order without a channel'));
    }
}
