<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\Exception\UndefinedRate;
use RoundingMode;

/**
 * Margin and markup: the amount, the rates, the conversion of one rate to the other, and the margin of many lines.
 *
 * The margin rate is the margin over the price; the markup is the margin over the cost. A price of 10.00 on a cost
 * of 6.00 has a margin of 4.00, a margin rate of 40 % and a markup of 66.6667 %.
 *
 * Every rate is rounded half away from zero unless a `\RoundingMode` is given: a refund, whose price and cost are
 * negated, has exactly the rates of the sale it reverses.
 */
final readonly class MarginCalculator
{
    /**
     * The price less the cost.
     */
    public function margin(Money $price, Money $cost): Money
    {
        return PricedLine::of($price, $cost)->margin();
    }

    /**
     * The margin over the price, in parts per million.
     *
     * @throws UndefinedRate when the price is zero
     */
    public function marginRate(Money $price, Money $cost, RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        return $this->summarize(PricedLine::of($price, $cost))->marginRate($mode);
    }

    /**
     * The margin over the cost, in parts per million.
     *
     * @throws UndefinedRate when the cost is zero
     */
    public function markupRate(Money $price, Money $cost, RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        return $this->summarize(PricedLine::of($price, $cost))->markupRate($mode);
    }

    /**
     * The markup that gives a margin rate: `markup = margin / (1 − margin)`. A margin of 40 % is a markup of
     * 66.6667 %.
     *
     * @throws InvalidRate when the margin is 100 % or more
     */
    public function markupFromMargin(Rate $margin, RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        return IntegerMath::rate($margin->partsPerMillion, IntegerMath::costShare($margin), $mode);
    }

    /**
     * The margin rate a markup gives: `margin = markup / (1 + markup)`. A markup of 100 % is a margin of 50 %.
     *
     * @throws InvalidRate when the markup is -100 % or less
     */
    public function marginFromMarkup(Rate $markup, RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        if ($markup->partsPerMillion < -Rate::ONE_MILLION) {
            throw InvalidRate::markupBelowMinusOneHundredPercent($markup->percent());
        }

        if (-Rate::ONE_MILLION === $markup->partsPerMillion) {
            throw InvalidRate::markupAtMinusOneHundredPercent();
        }

        return IntegerMath::rate($markup->partsPerMillion, $markup->partsPerMillion + Rate::ONE_MILLION, $mode);
    }

    /**
     * The totals of one or more lines and their rates, each line weighing by its amounts.
     *
     * @throws CurrencyMismatch when two lines are in different currencies
     */
    public function summarize(PricedLine $first, PricedLine ...$others): MarginSummary
    {
        $revenue = $first->revenue;
        $cost = $first->totalCost;

        foreach ($others as $line) {
            $revenue = $revenue->add($line->revenue);
            $cost = $cost->add($line->totalCost);
        }

        return new MarginSummary($revenue, $cost, $revenue->subtract($cost));
    }
}
