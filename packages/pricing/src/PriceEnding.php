<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Contract\PriceEndingRule;
use Phil\Pricing\Exception\InvalidPricing;
use RoundingMode;

/**
 * A price ending: the prices it allows repeat every step, at an offset. A step of 1.00 ending in 0.99 allows 0.99,
 * 1.99, 2.99...; ending in 0.95, 0.95, 1.95...; ending in 0.00, the whole units. A step of 10.00 ending in 9.99
 * allows 9.99, 19.99, 29.99...; a step of 0.50 ending in 0.49 allows 0.49, 0.99, 1.49...
 *
 * A price is moved to an allowed price by a `\RoundingMode`: up by default (`PositiveInfinity`), so a price
 * computed from a cost never loses margin; to the nearest with `HalfAwayFromZero`, a tie going up; down with
 * `NegativeInfinity`. Whatever the mode, the result is never below zero nor below the floor given: when the mode
 * would go below, the first allowed price at or above the floor is taken.
 */
final readonly class PriceEnding implements PriceEndingRule
{
    private function __construct(
        public Money $step,
        public Money $ending,
        public RoundingMode $mode,
    ) {
    }

    /**
     * @throws InvalidPricing   when the step is not above zero, or the ending is not
     *                          from zero to below the step
     * @throws CurrencyMismatch when the step and the ending differ in currency
     */
    public static function of(Money $step, Money $ending, RoundingMode $mode = RoundingMode::PositiveInfinity): self
    {
        if (!$step->isPositive()) {
            throw InvalidPricing::stepNotPositive($step->toDecimal());
        }

        if ($ending->isNegative() || !$ending->isLessThan($step)) {
            throw InvalidPricing::endingNotBelowStep($ending->toDecimal(), $step->toDecimal());
        }

        return new self($step, $ending, $mode);
    }

    /**
     * The same from decimals: `ofDecimals('1', '0.99', $currency)`.
     */
    public static function ofDecimals(string $step, string $ending, Currency $currency, RoundingMode $mode = RoundingMode::PositiveInfinity): self
    {
        return self::of(Money::ofDecimal($step, $currency), Money::ofDecimal($ending, $currency), $mode);
    }

    public function apply(Money $price, ?Money $floor = null): Money
    {
        if ($price->isNegative()) {
            throw InvalidPricing::negativePrice($price->toDecimal());
        }

        $lowest = Money::zero($price->currency);

        if ($floor instanceof Money && $floor->isGreaterThan($lowest)) {
            $lowest = $floor;
        }

        $ended = $this->nth(IntegerMath::divide($price->minorUnits - $this->ending->minorUnits, $this->step->minorUnits, $this->mode));

        if ($ended->isLessThan($lowest)) {
            return $this->nth(IntegerMath::divide($lowest->minorUnits - $this->ending->minorUnits, $this->step->minorUnits, RoundingMode::PositiveInfinity));
        }

        return $ended;
    }

    /**
     * The allowed price `n` steps after the ending.
     */
    private function nth(int $steps): Money
    {
        return Money::of(IntegerMath::multiply($steps, $this->step->minorUnits), $this->step->currency)->add($this->ending);
    }
}
