<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\RetailPriceComparison;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(RetailPriceComparison::class)]
#[CoversClass(InvalidPricing::class)]
final class RetailPriceComparisonTest extends TestCase
{
    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * Selling, suggested, difference, rate in ppm, rate below in ppm, below, above, at.
     *
     * @return iterable<string, array{int, int, int, int, int, bool, bool, bool}>
     */
    public static function comparisons(): iterable
    {
        yield '20 % below' => [2_000, 2_500, -500, -200_000, 200_000, true, false, false];
        yield '12.79 against 15.99' => [1_279, 1_599, -320, -200_125, 200_125, true, false, false];
        yield 'at the suggested price' => [2_500, 2_500, 0, 0, 0, false, false, true];
        yield '10 % above' => [2_750, 2_500, 250, 100_000, 0, false, true, false];
        yield 'one unit below' => [2_499, 2_500, -1, -400, 400, true, false, false];
        yield 'one unit above' => [2_501, 2_500, 1, 400, 0, false, true, false];
        yield 'given away' => [0, 2_500, -2_500, -1_000_000, 1_000_000, true, false, false];
        yield 'twice the suggested price' => [5_000, 2_500, 2_500, 1_000_000, 0, false, true, false];
    }

    #[DataProvider('comparisons')]
    public function testASellingPriceIsComparedToTheSuggestedPrice(int $selling, int $suggested, int $difference, int $rate, int $below, bool $isBelow, bool $isAbove, bool $isAt): void
    {
        $comparison = RetailPriceComparison::of($this->cad($selling), $this->cad($suggested));

        self::assertSame($selling, $comparison->sellingPrice->minorUnits);
        self::assertSame($suggested, $comparison->suggestedPrice->minorUnits);
        self::assertSame($difference, $comparison->difference()->minorUnits);
        self::assertSame($rate, $comparison->rate()->partsPerMillion);
        self::assertSame($below, $comparison->rateBelow()->partsPerMillion);
        self::assertSame($isBelow, $comparison->isBelow());
        self::assertSame($isAbove, $comparison->isAbove());
        self::assertSame($isAt, $comparison->isAtSuggestedPrice());
    }

    public function testTheRatesRoundHalfAwayFromZeroOrByTheModeGiven(): void
    {
        // 2.00 below 3.00 is 66.66666... %: 666 667 ppm to the nearest, 666 666 towards zero.
        $below = RetailPriceComparison::of($this->cad(100), $this->cad(300));
        $above = RetailPriceComparison::of($this->cad(500), $this->cad(300));

        self::assertSame(-666_667, $below->rate()->partsPerMillion);
        self::assertSame(-666_666, $below->rate(RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(666_667, $below->rateBelow()->partsPerMillion);
        self::assertSame(666_666, $below->rateBelow(RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(666_667, $above->rate()->partsPerMillion);
        self::assertSame(666_666, $above->rate(RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(0, $above->rateBelow(RoundingMode::TowardsZero)->partsPerMillion);
    }

    public function testTheRateOfAHalfPartPerMillionRoundsAwayFromZero(): void
    {
        // 1 unit below 2 000 000 is 0.5 ppm.
        $comparison = RetailPriceComparison::of($this->cad(1_999_999), $this->cad(2_000_000));

        self::assertSame(-1, $comparison->rate()->partsPerMillion);
        self::assertSame(1, $comparison->rateBelow()->partsPerMillion);
        self::assertSame(0, $comparison->rateBelow(RoundingMode::HalfTowardsZero)->partsPerMillion);
    }

    public function testAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        RetailPriceComparison::of(Money::of(2_000, Currency::of('USD')), $this->cad(2_500));
    }

    public function testAnotherCurrencyIsRefusedBeforeTheSign(): void
    {
        $this->expectException(CurrencyMismatch::class);

        RetailPriceComparison::of(Money::of(-2_000, Currency::of('USD')), $this->cad(0));
    }

    public function testANegativeSellingPriceIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A selling price of -0.01 and a suggested price of 25.00 have opposite signs: a selling price compared to a suggested one is zero or more.');

        RetailPriceComparison::of($this->cad(-1), $this->cad(2_500));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function suggestedPricesNotAboveZero(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-2_500];
    }

    #[DataProvider('suggestedPricesNotAboveZero')]
    public function testASuggestedPriceNotAboveZeroIsRefused(int $suggested): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested retail price is above zero, '.$this->cad($suggested)->toDecimal().' is not.');

        RetailPriceComparison::of($this->cad(2_000), $this->cad($suggested));
    }
}
