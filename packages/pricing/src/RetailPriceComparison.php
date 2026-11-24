<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidPricing;
use RoundingMode;

/**
 * A selling price against a suggested retail price: how far below or above it, in money and as a rate of the
 * suggested price. Selling at 7.99 what is suggested at 9.99 is 2.00, or 20.02 %, below the suggested price.
 */
final readonly class RetailPriceComparison
{
    private function __construct(
        public Money $sellingPrice,
        public Money $suggestedPrice,
    ) {
    }

    /**
     * @throws CurrencyMismatch when the two prices differ in currency
     * @throws InvalidPricing   when the selling price is negative or the suggested price is not above zero
     */
    public static function of(Money $sellingPrice, Money $suggestedPrice): self
    {
        $sellingPrice->compare($suggestedPrice);

        if (!$suggestedPrice->isPositive()) {
            throw InvalidPricing::suggestedPriceNotPositive($suggestedPrice->toDecimal());
        }

        if ($sellingPrice->isNegative()) {
            throw InvalidPricing::sellingPriceNegative($sellingPrice->toDecimal(), $suggestedPrice->toDecimal());
        }

        return new self($sellingPrice, $suggestedPrice);
    }

    /**
     * The selling price less the suggested price: negative below it, positive above it.
     */
    public function difference(): Money
    {
        return $this->sellingPrice->subtract($this->suggestedPrice);
    }

    /**
     * The difference over the suggested price, in parts per million: -200 000 is 20 % below the suggested price,
     * 100 000 is 10 % above it. Rounded half away from zero unless a `\RoundingMode` is given.
     */
    public function rate(RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        return IntegerMath::rate($this->difference()->minorUnits, $this->suggestedPrice->minorUnits, $mode);
    }

    /**
     * How far below the suggested price, as a positive rate — the "20 % off the suggested price" of a shelf tag —,
     * or zero at or above it.
     */
    public function rateBelow(RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        return $this->isBelow() ? IntegerMath::rate(-$this->difference()->minorUnits, $this->suggestedPrice->minorUnits, $mode) : Rate::zero();
    }

    public function isBelow(): bool
    {
        return $this->sellingPrice->isLessThan($this->suggestedPrice);
    }

    public function isAbove(): bool
    {
        return $this->sellingPrice->isGreaterThan($this->suggestedPrice);
    }

    public function isAtSuggestedPrice(): bool
    {
        return $this->sellingPrice->equals($this->suggestedPrice);
    }
}
