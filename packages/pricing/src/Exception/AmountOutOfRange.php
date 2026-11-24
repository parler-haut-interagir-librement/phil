<?php

declare(strict_types=1);

namespace Phil\Pricing\Exception;

use DomainException;

/**
 * A computation whose intermediate product leaves the range of a native integer: an amount above about 92 billion
 * units of a two-decimal currency times one million, for instance.
 */
final class AmountOutOfRange extends DomainException implements PricingException
{
    public static function intermediate(): self
    {
        return new self('The computation leaves the range of a native integer.');
    }
}
