<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\PriceCalculator;
use Phil\Pricing\Rate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(PriceCalculator::class)]
#[CoversClass(InvalidRate::class)]
final class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculator();
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * Cost, target margin in parts per million, price.
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function margins(): iterable
    {
        yield '6.00 at 40 %' => [600, 400_000, 1_000];
        yield '6.00 at 35 %, 9.2308 rounded up' => [600, 350_000, 924];
        yield '1.00 at 0 %' => [100, 0, 100];
        yield '1.00 at -100 %' => [100, -1_000_000, 50];
        yield '0.01 at 99.9999 %' => [1, 999_999, 1_000_000];
        yield 'a zero cost' => [0, 500_000, 0];
        yield '7.00 at 30 %, 10.00 exactly' => [700, 300_000, 1_000];
    }

    #[DataProvider('margins')]
    public function testThePriceForAMarginIsRoundedAwayFromZero(int $cost, int $margin, int $price): void
    {
        self::assertSame($price, $this->calculator->priceForMargin($this->cad($cost), Rate::of($margin))->minorUnits);
        self::assertSame(-$price, $this->calculator->priceForMargin($this->cad(-$cost), Rate::of($margin))->minorUnits);
    }

    public function testThePriceIsInTheCurrencyOfTheCost(): void
    {
        $price = $this->calculator->priceForMargin(Money::of(600, Currency::of('JPY')), Rate::of(350_000));

        self::assertSame(924, $price->minorUnits);
        self::assertSame('JPY', $price->currency->code);
        self::assertSame('EUR', $this->calculator->priceForMarkup(Money::of(1, Currency::of('EUR')), Rate::zero())->currency->code);
        self::assertSame('EUR', $this->calculator->priceForCostShare(Money::of(1, Currency::of('EUR')), Rate::of(1))->currency->code);
    }

    public function testTheRoundingOfAPriceIsChosen(): void
    {
        self::assertSame(923, $this->calculator->priceForMargin($this->cad(600), Rate::of(350_000), RoundingMode::HalfAwayFromZero)->minorUnits);
        self::assertSame(922, $this->calculator->priceForMarkup($this->cad(615), Rate::of(500_000), RoundingMode::TowardsZero)->minorUnits);
        self::assertSame(1_428, $this->calculator->priceForCostShare($this->cad(500), Rate::of(350_000), RoundingMode::NegativeInfinity)->minorUnits);
    }

    public function testATargetMarginOfOneHundredPercentIsRefused(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A margin of 100 % leaves no room for the cost: a target margin is below 100 %.');

        $this->calculator->priceForMargin($this->cad(600), Rate::of(1_000_000));
    }

    public function testATargetMarginAboveOneHundredPercentIsRefused(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A margin of 150 % leaves no room for the cost: a target margin is below 100 %.');

        $this->calculator->priceForMargin($this->cad(600), Rate::of(1_500_000));
    }

    public function testACostTooLargeForAMarginIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        $this->calculator->priceForMargin($this->cad(intdiv(\PHP_INT_MAX, 1_000_000) + 1), Rate::zero());
    }

    public function testTheLargestCostForAMarginIsPriced(): void
    {
        $cost = intdiv(\PHP_INT_MAX, 1_000_000);

        self::assertSame($cost, $this->calculator->priceForMargin($this->cad($cost), Rate::zero())->minorUnits);
    }

    /**
     * Cost, target markup in parts per million, price.
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function markups(): iterable
    {
        yield '6.00 at 50 %' => [600, 500_000, 900];
        yield '6.15 at 50 %, 9.225 rounded up' => [615, 500_000, 923];
        yield '6.00 at 0 %' => [600, 0, 600];
        yield '6.00 at -100 %' => [600, -1_000_000, 0];
        yield '6.00 at 250 %' => [600, 2_500_000, 2_100];
        yield '0.01 at 0.0001 %, rounded up to 0.02' => [1, 1, 2];
    }

    #[DataProvider('markups')]
    public function testThePriceForAMarkupIsRoundedAwayFromZero(int $cost, int $markup, int $price): void
    {
        self::assertSame($price, $this->calculator->priceForMarkup($this->cad($cost), Rate::of($markup))->minorUnits);
        self::assertSame(-$price, $this->calculator->priceForMarkup($this->cad(-$cost), Rate::of($markup))->minorUnits);
    }

    public function testAMarkupBelowMinusOneHundredPercentIsRefused(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A markup of -100.0001 % gives a price of the opposite sign: a markup is -100 % or more.');

        $this->calculator->priceForMarkup($this->cad(600), Rate::of(-1_000_001));
    }

    /**
     * Cost, share of the price in parts per million, price.
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function shares(): iterable
    {
        yield '3.00 at 30 %' => [300, 300_000, 1_000];
        yield '5.00 at 35 %, 14.2857 rounded up' => [500, 350_000, 1_429];
        yield '5.00 at 100 %' => [500, 1_000_000, 500];
        yield '5.00 at 200 %' => [500, 2_000_000, 250];
        yield '0.01 at 0.0001 %' => [1, 1, 1_000_000];
    }

    #[DataProvider('shares')]
    public function testThePriceForACostShareIsRoundedAwayFromZero(int $cost, int $share, int $price): void
    {
        self::assertSame($price, $this->calculator->priceForCostShare($this->cad($cost), Rate::of($share))->minorUnits);
        self::assertSame(-$price, $this->calculator->priceForCostShare($this->cad(-$cost), Rate::of($share))->minorUnits);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function nonPositiveShares(): iterable
    {
        yield 'zero' => [0, '0'];
        yield 'negative' => [-1, '-0.0001'];
    }

    #[DataProvider('nonPositiveShares')]
    public function testACostShareOfZeroOrLessIsRefused(int $share, string $percent): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs(\sprintf('A cost share of %s %% gives no price: a cost share is above 0 %%.', $percent));

        $this->calculator->priceForCostShare($this->cad(500), Rate::of($share));
    }
}
