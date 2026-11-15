<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A change or an adjustment names a line the order does not have.
 */
final class LineNotInOrder extends DomainException implements DomainError
{
    public static function of(string $orderNumber, SellableReference $reference): self
    {
        return new self(\sprintf('The order %s has no line %s.', $orderNumber, $reference->value()));
    }
}
