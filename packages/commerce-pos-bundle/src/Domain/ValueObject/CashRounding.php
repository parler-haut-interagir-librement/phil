<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * Rounding a cash amount to the coins in use: to the nearest five cents in Canada since the penny was withdrawn —
 * 1.01 and 1.02 down to 1.00, 1.03 and 1.04 up to 1.05. Only cash rounds; the order, its taxes and its payment keep
 * their exact amounts, and the drawer counts the difference.
 */
final readonly class CashRounding
{
    private function __construct(
        public int $increment,
    ) {
    }

    /**
     * @param int $increment the smallest cash amount, in minor units: 5 in Canada, 1 where every coin is in use
     */
    public static function of(int $increment): self
    {
        if ($increment < 1 || $increment > 100) {
            throw InvalidValue::because(\sprintf('A cash increment is 1 to 100 minor units; %d is refused.', $increment));
        }

        return new self($increment);
    }

    /**
     * The amount to the nearest increment, halves up; a negative amount rounds as its opposite would.
     */
    public function round(Money $amount): Money
    {
        $units = abs($amount->minorUnits);
        $remainder = $units % $this->increment;
        $rounded = $units - $remainder + (2 * $remainder >= $this->increment ? $this->increment : 0);

        return Money::of($amount->isNegative() ? -$rounded : $rounded, $amount->currency);
    }
}
