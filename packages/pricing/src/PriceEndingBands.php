<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Contract\PriceEndingRule;
use Phil\Pricing\Exception\InvalidPricing;

/**
 * Endings by price band: one rule below the first threshold, another from it, another from the next. For instance
 * 0.99 below 10.00, 0.95 from 10.00, the whole unit from 100.00.
 *
 * The band is chosen by the price before it is ended; the rule of that band ends it, even when the ended price
 * crosses into the next band. Bands are added in ascending order of their thresholds, never ranked by a number.
 */
final readonly class PriceEndingBands implements PriceEndingRule
{
    /**
     * @param list<array{Money, PriceEndingRule}> $bands
     */
    private function __construct(
        private PriceEndingRule $first,
        private array $bands,
    ) {
    }

    /**
     * The rule of every price below the first threshold, or of every price when no band is added.
     */
    public static function startingWith(PriceEndingRule $first): self
    {
        return new self($first, []);
    }

    /**
     * A new set of bands, with `$rule` for the prices from `$threshold`, which is above the previous threshold.
     *
     * @throws InvalidPricing   when the threshold is not above zero and above the
     *                          previous one
     * @throws CurrencyMismatch when it differs in currency from the previous one
     */
    public function from(Money $threshold, PriceEndingRule $rule): self
    {
        if (!$threshold->isPositive()) {
            throw InvalidPricing::bandNotPositive($threshold->toDecimal());
        }

        $last = array_last($this->bands);

        if (null !== $last && !$threshold->isGreaterThan($last[0])) {
            throw InvalidPricing::bandsOutOfOrder($threshold->toDecimal(), $last[0]->toDecimal());
        }

        return new self($this->first, [...$this->bands, [$threshold, $rule]]);
    }

    public function apply(Money $price, ?Money $floor = null): Money
    {
        $rule = $this->first;

        foreach ($this->bands as [$threshold, $bandRule]) {
            if (!$price->isLessThan($threshold)) {
                $rule = $bandRule;
            }
        }

        return $rule->apply($price, $floor);
    }
}
