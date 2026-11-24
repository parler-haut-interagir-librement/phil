<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;
use Phil\Pricing\Exception\UndefinedRate;
use RoundingMode;

/**
 * The totals of one or more lines: revenue, cost and margin, and the rates they give.
 *
 * The rates are computed on the totals, so each line weighs by its amounts: the margin rate of a category is its
 * total margin over its total revenue, never the average of the rates of its lines.
 */
final readonly class MarginSummary
{
    public function __construct(
        public Money $revenue,
        public Money $cost,
        public Money $margin,
    ) {
    }

    /**
     * The margin over the revenue, in parts per million, rounded half away from zero unless told otherwise.
     *
     * @throws UndefinedRate when the revenue is zero
     */
    public function marginRate(RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        if ($this->revenue->isZero()) {
            throw UndefinedRate::zeroPrice();
        }

        return IntegerMath::rate($this->margin->minorUnits, $this->revenue->minorUnits, $mode);
    }

    /**
     * The margin over the cost — the markup —, in parts per million, rounded half away from zero unless told
     * otherwise.
     *
     * @throws UndefinedRate when the cost is zero
     */
    public function markupRate(RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        if ($this->cost->isZero()) {
            throw UndefinedRate::zeroCost();
        }

        return IntegerMath::rate($this->margin->minorUnits, $this->cost->minorUnits, $mode);
    }
}
