<?php

declare(strict_types=1);

namespace Phil\Pricing\Exception;

use DomainException;

/**
 * Amounts that cannot be priced together: a price and a cost of opposite signs, a negative price given a price
 * ending, an ending that is not below its step, price bands out of order, a suggested or wholesale price from a cost
 * that is not above zero, quantity tiers out of order or that raise the price.
 */
final class InvalidPricing extends DomainException implements PricingException
{
    public static function oppositeSigns(string $price, string $cost): self
    {
        return new self(\sprintf('A price of %s and a cost of %s have opposite signs: a refund negates both.', $price, $cost));
    }

    public static function negativePrice(string $price): self
    {
        return new self(\sprintf('A price ending applies to a selling price, %s is negative: a refund negates the ended price.', $price));
    }

    public static function stepNotPositive(string $step): self
    {
        return new self(\sprintf('A price ending repeats every step, %s is not above zero.', $step));
    }

    public static function endingNotBelowStep(string $ending, string $step): self
    {
        return new self(\sprintf('The ending %s is not from zero to below its step %s.', $ending, $step));
    }

    public static function bandsOutOfOrder(string $threshold, string $previous): self
    {
        return new self(\sprintf('The band from %s does not start above the band from %s: bands are added in ascending order.', $threshold, $previous));
    }

    public static function bandNotPositive(string $threshold): self
    {
        return new self(\sprintf('A band starts above zero, %s does not: the first ending covers every price below the first band.', $threshold));
    }

    public static function zeroCost(): self
    {
        return new self('A suggested or wholesale price marks up a cost: a zero cost gives none.');
    }

    public static function negativeCost(string $cost): self
    {
        return new self(\sprintf('A suggested or wholesale price marks up a cost above zero, %s is negative: a refund negates the price, not the cost.', $cost));
    }

    public static function suggestedPriceNotPositive(string $price): self
    {
        return new self(\sprintf('A suggested retail price is above zero, %s is not.', $price));
    }

    public static function sellingPriceNegative(string $sellingPrice, string $suggestedPrice): self
    {
        return new self(\sprintf('A selling price of %s and a suggested price of %s have opposite signs: a selling price compared to a suggested one is zero or more.', $sellingPrice, $suggestedPrice));
    }

    public static function quantityNotPositive(int $quantity): self
    {
        return new self(\sprintf('A wholesale quantity is 1 or more, %d is not.', $quantity));
    }

    public static function tiersOutOfOrder(int $quantity, int $previous): self
    {
        return new self(\sprintf('The tier from %d units does not start above the tier from %d units: tiers are added in ascending order.', $quantity, $previous));
    }

    public static function tierRaisesPrice(int $quantity, string $unitPrice, string $previous): self
    {
        return new self(\sprintf('The tier from %d units costs %s a unit, more than the %s of the tier before it: a larger quantity never raises the unit price.', $quantity, $unitPrice, $previous));
    }

    public static function unitPriceNotPositive(string $unitPrice): self
    {
        return new self(\sprintf('A wholesale unit price is above zero, %s is not.', $unitPrice));
    }
}
