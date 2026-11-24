<?php

declare(strict_types=1);

namespace Phil\Pricing\Exception;

use DomainException;

/**
 * A rate asked of a zero base: the margin rate of a zero price, the markup of a zero cost, the effective discount of
 * a zero price. Such a rate has no value; the library never invents one.
 */
final class UndefinedRate extends DomainException implements PricingException
{
    public static function zeroPrice(): self
    {
        return new self('The margin rate of a zero price is undefined.');
    }

    public static function zeroCost(): self
    {
        return new self('The markup on a zero cost is undefined.');
    }

    public static function zeroOriginalPrice(): self
    {
        return new self('The effective discount on a zero price is undefined.');
    }
}
