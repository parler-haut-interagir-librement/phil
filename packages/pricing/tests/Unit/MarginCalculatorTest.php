<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\Exception\UndefinedRate;
use Phil\Pricing\MarginCalculator;
use Phil\Pricing\MarginSummary;
use Phil\Pricing\PricedLine;
use Phil\Pricing\Rate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(MarginCalculator::class)]
#[CoversClass(MarginSummary::class)]
#[CoversClass(PricedLine::class)]
#[CoversClass(InvalidPricing::class)]
#[CoversClass(UndefinedRate::class)]
#[CoversClass(InvalidRate::class)]
final class MarginCalculatorTest extends TestCase
{
    private MarginCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new MarginCalculator();
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * Price, cost, margin, margin rate and markup, in minor units and parts per million, rounded half away from zero.
     *
     * @return iterable<string, array{int, int, int, int, int}>
     */
    public static function sales(): iterable
    {
        yield '10.00 on 6.00' => [1_000, 600, 400, 400_000, 666_667];
        yield '9.00 on 6.00' => [900, 600, 300, 333_333, 500_000];
        yield 'at cost' => [600, 600, 0, 0, 0];
        yield 'below cost' => [500, 600, -100, -200_000, -166_667];
        yield 'twice the cost' => [1_200, 600, 600, 500_000, 1_000_000];
        yield 'free, from cost' => [0, 600, -600, \PHP_INT_MAX, -1_000_000];
        yield 'a third, rounded up' => [3, 1, 2, 666_667, 2_000_000];
        yield 'an exact half of a part per million, away from zero' => [2_000_000, 1_999_999, 1, 1, 1];
    }

    #[DataProvider('sales')]
    public function testTheMarginIsThePriceLessTheCost(int $price, int $cost, int $margin, int $rate, int $markup): void
    {
        self::assertSame($margin, $this->calculator->margin($this->cad($price), $this->cad($cost))->minorUnits);
        self::assertSame(-$margin, $this->calculator->margin($this->cad(-$price), $this->cad(-$cost))->minorUnits);
    }

    #[DataProvider('sales')]
    public function testTheMarginRateIsTheMarginOverThePrice(int $price, int $cost, int $margin, int $rate, int $markup): void
    {
        if (0 === $price) {
            $this->expectException(UndefinedRate::class);
            $this->expectExceptionMessageIs('The margin rate of a zero price is undefined.');
        }

        self::assertSame($rate, $this->calculator->marginRate($this->cad($price), $this->cad($cost))->partsPerMillion);
    }

    #[DataProvider('sales')]
    public function testARefundHasTheMarginRateOfTheSale(int $price, int $cost, int $margin, int $rate, int $markup): void
    {
        if (0 === $price) {
            $this->expectException(UndefinedRate::class);
        }

        self::assertSame($rate, $this->calculator->marginRate($this->cad(-$price), $this->cad(-$cost))->partsPerMillion);
    }

    #[DataProvider('sales')]
    public function testTheMarkupIsTheMarginOverTheCost(int $price, int $cost, int $margin, int $rate, int $markup): void
    {
        self::assertSame($markup, $this->calculator->markupRate($this->cad($price), $this->cad($cost))->partsPerMillion);
        self::assertSame($markup, $this->calculator->markupRate($this->cad(-$price), $this->cad(-$cost))->partsPerMillion);
    }

    public function testTheRoundingOfARateIsChosen(): void
    {
        self::assertSame(666_666, $this->calculator->markupRate($this->cad(1_000), $this->cad(600), RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(333_334, $this->calculator->marginRate($this->cad(900), $this->cad(600), RoundingMode::PositiveInfinity)->partsPerMillion);
        self::assertSame(0, $this->calculator->marginRate($this->cad(2_000_000), $this->cad(1_999_999), RoundingMode::HalfTowardsZero)->partsPerMillion);
        self::assertSame(0, $this->calculator->markupRate($this->cad(2_000_000), $this->cad(1_999_999), RoundingMode::TowardsZero)->partsPerMillion);
    }

    public function testTheMarkupOfAZeroCostIsUndefined(): void
    {
        $this->expectException(UndefinedRate::class);
        $this->expectExceptionMessageIs('The markup on a zero cost is undefined.');

        $this->calculator->markupRate($this->cad(1_000), $this->cad(0));
    }

    public function testAZeroCostHasAFullMargin(): void
    {
        self::assertSame(1_000_000, $this->calculator->marginRate($this->cad(1_000), $this->cad(0))->partsPerMillion);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function oppositeSigns(): iterable
    {
        yield 'a positive price on a negative cost' => [1_000, -600];
        yield 'a negative price on a positive cost' => [-1_000, 600];
    }

    #[DataProvider('oppositeSigns')]
    public function testAPriceAndACostOfOppositeSignsAreRefused(int $price, int $cost): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIs(\sprintf('A price of %s and a cost of %s have opposite signs: a refund negates both.', $this->cad($price)->toDecimal(), $this->cad($cost)->toDecimal()));

        $this->calculator->margin($this->cad($price), $this->cad($cost));
    }

    public function testAZeroPriceOrCostTakesEitherSign(): void
    {
        self::assertSame(600, $this->calculator->margin($this->cad(0), $this->cad(-600))->minorUnits);
        self::assertSame(-1_000, $this->calculator->margin($this->cad(-1_000), $this->cad(0))->minorUnits);
    }

    public function testAmountsInDifferentCurrenciesAreRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        PricedLine::of($this->cad(1_000), Money::of(600, Currency::of('USD')));
    }

    public function testAMarginTooLargeForARateIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        $this->calculator->marginRate($this->cad(\PHP_INT_MAX), $this->cad(0));
    }

    /**
     * Margin rate and markup, both ways, rounded half away from zero.
     *
     * @return iterable<string, array{int, int}>
     */
    public static function conversions(): iterable
    {
        yield 'zero' => [0, 0];
        yield '40 % margin, 66.6667 % markup' => [400_000, 666_667];
        yield '50 % margin, 100 % markup' => [500_000, 1_000_000];
        yield '20 % margin, 25 % markup' => [200_000, 250_000];
        yield '-100 % margin, -50 % markup' => [-1_000_000, -500_000];
        yield '75 % margin, 300 % markup' => [750_000, 3_000_000];
        yield '99.9999 % margin' => [999_999, 999_999_000_000];
    }

    #[DataProvider('conversions')]
    public function testAMarginConvertsToAMarkup(int $margin, int $markup): void
    {
        self::assertSame($markup, $this->calculator->markupFromMargin(Rate::of($margin))->partsPerMillion);
    }

    #[DataProvider('conversions')]
    public function testAMarkupConvertsToAMargin(int $margin, int $markup): void
    {
        self::assertSame($margin, $this->calculator->marginFromMarkup(Rate::of($markup))->partsPerMillion);
    }

    public function testTheRoundingOfAConversionIsChosen(): void
    {
        self::assertSame(666_666, $this->calculator->markupFromMargin(Rate::of(400_000), RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(400_000, $this->calculator->marginFromMarkup(Rate::of(666_667), RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(400_001, $this->calculator->marginFromMarkup(Rate::of(666_667), RoundingMode::PositiveInfinity)->partsPerMillion);
    }

    public function testATargetMarginOfOneHundredPercentHasNoMarkup(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A margin of 100 % leaves no room for the cost: a target margin is below 100 %.');

        $this->calculator->markupFromMargin(Rate::of(1_000_000));
    }

    public function testAMarkupOfMinusOneHundredPercentHasNoMargin(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A markup of -100 % is a zero price, whose margin is undefined.');

        $this->calculator->marginFromMarkup(Rate::of(-1_000_000));
    }

    public function testAMarkupBelowMinusOneHundredPercentIsRefused(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A markup of -100.0001 % gives a price of the opposite sign: a markup is -100 % or more.');

        $this->calculator->marginFromMarkup(Rate::of(-1_000_001));
    }

    public function testAMarkupJustAboveMinusOneHundredPercentHasAMargin(): void
    {
        self::assertSame(-999_999_000_000, $this->calculator->marginFromMarkup(Rate::of(-999_999))->partsPerMillion);
    }

    public function testLinesAreSummedByTheirAmounts(): void
    {
        $summary = $this->calculator->summarize(
            PricedLine::of($this->cad(1_000), $this->cad(600), 3),
            PricedLine::of($this->cad(200), $this->cad(50)),
            PricedLine::of($this->cad(1_000), $this->cad(600), -1),
        );

        self::assertSame(2_200, $summary->revenue->minorUnits);
        self::assertSame(1_250, $summary->cost->minorUnits);
        self::assertSame(950, $summary->margin->minorUnits);
        self::assertSame(431_818, $summary->marginRate()->partsPerMillion);
        self::assertSame(760_000, $summary->markupRate()->partsPerMillion);
        self::assertSame(431_819, $summary->marginRate(RoundingMode::PositiveInfinity)->partsPerMillion);
        self::assertSame(760_000, $summary->markupRate(RoundingMode::TowardsZero)->partsPerMillion);
    }

    public function testTheBlendedMarginIsNotTheAverageOfTheRates(): void
    {
        // 90 % on 1.00 and 10 % on 100.00: the average of the rates is 50 %, the margin of the sales is 10.79 %.
        $summary = $this->calculator->summarize(
            PricedLine::of($this->cad(100), $this->cad(10)),
            PricedLine::of($this->cad(10_000), $this->cad(9_000)),
        );

        self::assertSame(107_921, $summary->marginRate()->partsPerMillion);
    }

    public function testASingleLineIsItsOwnSummary(): void
    {
        $line = PricedLine::of($this->cad(1_000), $this->cad(600), 2);
        $summary = $this->calculator->summarize($line);

        self::assertSame(2_000, $summary->revenue->minorUnits);
        self::assertSame(1_200, $summary->cost->minorUnits);
        self::assertSame(800, $line->margin()->minorUnits);
        self::assertSame(1_000, $line->price->minorUnits);
        self::assertSame(600, $line->cost->minorUnits);
        self::assertSame(2, $line->quantity);
    }

    public function testASummaryWithoutRevenueHasNoMarginRate(): void
    {
        $summary = $this->calculator->summarize(PricedLine::of($this->cad(1_000), $this->cad(600)), PricedLine::of($this->cad(1_000), $this->cad(600), -1));

        $this->expectException(UndefinedRate::class);

        $summary->marginRate();
    }

    public function testASummaryWithoutCostHasNoMarkup(): void
    {
        $summary = $this->calculator->summarize(PricedLine::of($this->cad(1_000), $this->cad(0)));

        $this->expectException(UndefinedRate::class);

        $summary->markupRate();
    }

    public function testLinesInDifferentCurrenciesAreRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->calculator->summarize(
            PricedLine::of($this->cad(1_000), $this->cad(600)),
            PricedLine::of(Money::of(1_000, Currency::of('USD')), Money::of(0, Currency::of('USD'))),
        );
    }

    public function testCostsInDifferentCurrenciesAreRefusedInASummary(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->calculator->summarize(
            PricedLine::of($this->cad(0), $this->cad(600)),
            PricedLine::of(Money::of(0, Currency::of('USD')), Money::of(600, Currency::of('USD'))),
        );
    }
}
