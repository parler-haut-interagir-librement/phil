<?php

declare(strict_types=1);

namespace Phil\Tax\Exception;

use DomainException;

/**
 * A base whose tax cannot be computed in a native integer: the product of the base and a rate would overflow.
 */
final class UntaxableBase extends DomainException implements TaxException
{
    public static function tooLarge(int $largest): self
    {
        return new self(\sprintf('The taxable base is too large to be taxed in a native integer: %d minor units at most.', $largest));
    }
}
