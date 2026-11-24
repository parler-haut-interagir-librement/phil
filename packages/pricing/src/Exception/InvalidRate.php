<?php

declare(strict_types=1);

namespace Phil\Pricing\Exception;

use InvalidArgumentException;

/**
 * A rate that cannot be read, or that has no meaning where it is given: a target margin of 100 % or more, a markup
 * below -100 %, a discount outside 0 to 100 %, a cost share of zero or less, a markup that leaves no suggested or wholesale price.
 */
final class InvalidRate extends InvalidArgumentException implements PricingException
{
    public static function notAPercent(string $percent): self
    {
        return new self(\sprintf('"%s" is not a percentage: digits, with an optional "-" and up to four decimals, are expected.', $percent));
    }

    public static function outOfRange(): self
    {
        return new self('The rate leaves the range of a native integer.');
    }

    public static function marginNotBelowOneHundredPercent(string $percent): self
    {
        return new self(\sprintf('A margin of %s %% leaves no room for the cost: a target margin is below 100 %%.', $percent));
    }

    public static function markupBelowMinusOneHundredPercent(string $percent): self
    {
        return new self(\sprintf('A markup of %s %% gives a price of the opposite sign: a markup is -100 %% or more.', $percent));
    }

    public static function markupAtMinusOneHundredPercent(): self
    {
        return new self('A markup of -100 % is a zero price, whose margin is undefined.');
    }

    public static function discountOutOfRange(string $percent): self
    {
        return new self(\sprintf('A discount of %s %% is not one: a discount is from 0 to 100 %%.', $percent));
    }

    public static function costShareNotPositive(string $percent): self
    {
        return new self(\sprintf('A cost share of %s %% gives no price: a cost share is above 0 %%.', $percent));
    }

    public static function markupGivesNoPrice(string $percent): self
    {
        return new self(\sprintf('A markup of %s %% leaves no price above zero: a suggested or wholesale price comes from a markup above -100 %%.', $percent));
    }
}
