<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\MarkedUpPrice;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\Rate;
use Phil\Pricing\RetailPriceComparison;
use Phil\Pricing\SuggestedRetailPrice;
use Phil\Pricing\SuggestedRetailPriceSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(SuggestedRetailPrice::class)]
#[CoversClass(SuggestedRetailPriceSource::class)]
#[CoversClass(MarkedUpPrice::class)]
#[CoversClass(InvalidPricing::class)]
#[CoversClass(InvalidRate::class)]
final class SuggestedRetailPriceTest extends TestCase
{
    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    private function ninetyNine(RoundingMode $mode = RoundingMode::PositiveInfinity): PriceEnding
    {
        return PriceEnding::ofDecimals('1', '0.99', Currency::of('CAD'), $mode);
    }

    public function testASupplierPriceIsKeptAsGiven(): void
    {
        $suggested = SuggestedRetailPrice::fromSupplier($this->cad(2_499));

        self::assertSame(2_499, $suggested->price->minorUnits);
        self::assertSame(2_499, $suggested->unendedPrice->minorUnits);
        self::assertSame(SuggestedRetailPriceSource::Supplier, $suggested->source);
        self::assertNull($suggested->basis);
        self::assertNull($suggested->markup);
        self::assertFalse($suggested->isKeystone());
    }

    public function testTheSmallestSupplierPriceIsOneUnit(): void
    {
        self::assertSame(1, SuggestedRetailPrice::fromSupplier($this->cad(1))->price->minorUnits);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function supplierPricesNotAboveZero(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-2_499];
    }

    #[DataProvider('supplierPricesNotAboveZero')]
    public function testASupplierPriceNotAboveZeroIsRefused(int $price): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested retail price is above zero, '.$this->cad($price)->toDecimal().' is not.');

        SuggestedRetailPrice::fromSupplier($this->cad($price));
    }

    /**
     * Cost, markup in ppm, suggested price — rounded away from zero.
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function costMarkups(): iterable
    {
        yield '6.00 at 60 %' => [600, 600_000, 960];
        yield '6.00 at 35 %' => [600, 350_000, 810];
        yield '6.37 at 35 %, 8.5995 up' => [637, 350_000, 860];
        yield '6.00 at 0 %' => [600, 0, 600];
        yield '6.00 at -50 %' => [600, -500_000, 300];
        yield '1 unit at -99.9999 %, up to 1' => [1, -999_999, 1];
        yield '6.00 at 250 %' => [600, 2_500_000, 2_100];
    }

    #[DataProvider('costMarkups')]
    public function testAPriceFromACostIsTheCostMarkedUp(int $cost, int $markup, int $price): void
    {
        $suggested = SuggestedRetailPrice::fromCost($this->cad($cost), Rate::of($markup));

        self::assertSame($price, $suggested->price->minorUnits);
        self::assertSame($price, $suggested->unendedPrice->minorUnits);
        self::assertSame(SuggestedRetailPriceSource::CostMarkup, $suggested->source);
        self::assertSame($cost, $suggested->basis?->minorUnits);
        self::assertSame($markup, $suggested->markup?->partsPerMillion);
    }

    public function testAPriceFromACostRoundsByTheModeGiven(): void
    {
        self::assertSame(859, SuggestedRetailPrice::fromCost($this->cad(637), Rate::ofPercent('35'), RoundingMode::TowardsZero)->price->minorUnits);
        self::assertSame(860, SuggestedRetailPrice::fromCost($this->cad(637), Rate::ofPercent('35'), RoundingMode::HalfAwayFromZero)->price->minorUnits);
    }

    public function testAPriceThatRoundsToZeroIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested retail price is above zero, 0.00 is not.');

        SuggestedRetailPrice::fromCost($this->cad(1), Rate::of(-999_999), RoundingMode::TowardsZero);
    }

    public function testKeystoneIsTwiceTheCost(): void
    {
        $suggested = SuggestedRetailPrice::keystone($this->cad(600));

        self::assertSame(1_200, $suggested->price->minorUnits);
        self::assertSame(SuggestedRetailPriceSource::CostMarkup, $suggested->source);
        self::assertSame(1_000_000, $suggested->markup?->partsPerMillion);
        self::assertSame(1_000_000, SuggestedRetailPrice::KEYSTONE_MARKUP);
        self::assertTrue($suggested->isKeystone());
    }

    public function testACostMarkupOfOneHundredPercentIsKeystone(): void
    {
        self::assertTrue(SuggestedRetailPrice::fromCost($this->cad(600), Rate::ofPercent('100'))->isKeystone());
        self::assertFalse(SuggestedRetailPrice::fromCost($this->cad(600), Rate::of(999_999))->isKeystone());
        self::assertFalse(SuggestedRetailPrice::fromCost($this->cad(600), Rate::of(1_000_001))->isKeystone());
    }

    public function testAWholesaleMarkupOfOneHundredPercentIsNotKeystoneOnTheCost(): void
    {
        self::assertFalse(SuggestedRetailPrice::fromWholesale($this->cad(780), Rate::ofPercent('100'))->isKeystone());
    }

    public function testAPriceFromAWholesalePriceIsItMarkedUp(): void
    {
        $suggested = SuggestedRetailPrice::fromWholesale($this->cad(780), Rate::ofPercent('100'));

        self::assertSame(1_560, $suggested->price->minorUnits);
        self::assertSame(SuggestedRetailPriceSource::WholesaleMarkup, $suggested->source);
        self::assertSame(780, $suggested->basis?->minorUnits);
        self::assertSame(1_000_000, $suggested->markup?->partsPerMillion);
    }

    public function testAPriceFromAWholesalePriceRoundsAwayFromZeroOrByTheModeGiven(): void
    {
        self::assertSame(860, SuggestedRetailPrice::fromWholesale($this->cad(637), Rate::ofPercent('35'))->price->minorUnits);
        self::assertSame(859, SuggestedRetailPrice::fromWholesale($this->cad(637), Rate::ofPercent('35'), RoundingMode::TowardsZero)->price->minorUnits);
    }

    public function testAZeroCostIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested or wholesale price marks up a cost: a zero cost gives none.');

        SuggestedRetailPrice::fromCost($this->cad(0), Rate::ofPercent('60'));
    }

    public function testAZeroCostIsRefusedForKeystone(): void
    {
        $this->expectException(InvalidPricing::class);

        SuggestedRetailPrice::keystone($this->cad(0));
    }

    public function testANegativeCostIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested or wholesale price marks up a cost above zero, -6.00 is negative: a refund negates the price, not the cost.');

        SuggestedRetailPrice::fromCost($this->cad(-600), Rate::ofPercent('60'));
    }

    public function testAZeroWholesalePriceIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);

        SuggestedRetailPrice::fromWholesale($this->cad(0), Rate::ofPercent('100'));
    }

    public function testANegativeWholesalePriceIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);

        SuggestedRetailPrice::fromWholesale($this->cad(-780), Rate::ofPercent('100'));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function markupsGivingNoPrice(): iterable
    {
        yield '-100 %' => [-1_000_000, '-100'];
        yield 'below -100 %' => [-1_000_001, '-100.0001'];
    }

    #[DataProvider('markupsGivingNoPrice')]
    public function testAMarkupOfMinusOneHundredPercentOrLessIsRefused(int $markup, string $percent): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A markup of %s %% leaves no price above zero: a suggested or wholesale price comes from a markup above -100 %%.', $percent));

        SuggestedRetailPrice::fromCost($this->cad(600), Rate::of($markup));
    }

    #[DataProvider('markupsGivingNoPrice')]
    public function testAWholesaleMarkupOfMinusOneHundredPercentOrLessIsRefused(int $markup, string $percent): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A markup of %s %%', $percent));

        SuggestedRetailPrice::fromWholesale($this->cad(780), Rate::of($markup));
    }

    public function testACostTooLargeToMarkUpIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        SuggestedRetailPrice::fromCost($this->cad(\PHP_INT_MAX), Rate::ofPercent('60'));
    }

    public function testAnEndedPriceKeepsTheUnendedOneAndItsOrigin(): void
    {
        $suggested = SuggestedRetailPrice::fromWholesale($this->cad(780), Rate::ofPercent('100'))->ended($this->ninetyNine());

        self::assertSame(1_599, $suggested->price->minorUnits);
        self::assertSame(1_560, $suggested->unendedPrice->minorUnits);
        self::assertSame(SuggestedRetailPriceSource::WholesaleMarkup, $suggested->source);
        self::assertSame(780, $suggested->basis?->minorUnits);
        self::assertSame(1_000_000, $suggested->markup?->partsPerMillion);
    }

    public function testEndingAgainStartsFromTheUnendedPrice(): void
    {
        $once = SuggestedRetailPrice::fromCost($this->cad(600), Rate::ofPercent('60'))->ended($this->ninetyNine());
        $twice = $once->ended(PriceEnding::ofDecimals('1', '0.95', Currency::of('CAD'), RoundingMode::NegativeInfinity));

        self::assertSame(999, $once->price->minorUnits);
        self::assertSame(895, $twice->price->minorUnits);
        self::assertSame(960, $twice->unendedPrice->minorUnits);
    }

    public function testAnEndingNeverGoesBelowTheBasis(): void
    {
        // 6.00 at -50 % is 3.00, ended down at 2.99: below the cost of 6.00, so the first .99 above it.
        $suggested = SuggestedRetailPrice::fromCost($this->cad(600), Rate::ofPercent('-50'))->ended($this->ninetyNine(RoundingMode::NegativeInfinity));

        self::assertSame(699, $suggested->price->minorUnits);
    }

    public function testAnEndingTakesTheFloorGivenInsteadOfTheBasis(): void
    {
        $suggested = SuggestedRetailPrice::fromCost($this->cad(600), Rate::ofPercent('60'));

        self::assertSame(899, $suggested->ended($this->ninetyNine(RoundingMode::NegativeInfinity))->price->minorUnits);
        self::assertSame(1_099, $suggested->ended($this->ninetyNine(RoundingMode::NegativeInfinity), $this->cad(1_000))->price->minorUnits);
        self::assertSame(899, $suggested->ended($this->ninetyNine(RoundingMode::NegativeInfinity), $this->cad(100))->price->minorUnits);
    }

    public function testASupplierPriceIsEndedWithoutAFloor(): void
    {
        $suggested = SuggestedRetailPrice::fromSupplier($this->cad(2_437))->ended($this->ninetyNine(RoundingMode::NegativeInfinity));

        self::assertSame(2_399, $suggested->price->minorUnits);
        self::assertSame(SuggestedRetailPriceSource::Supplier, $suggested->source);
        self::assertNull($suggested->basis);
        self::assertNull($suggested->markup);
    }

    public function testAnEndingToZeroIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested retail price is above zero, 0.00 is not.');

        SuggestedRetailPrice::fromSupplier($this->cad(50))->ended(PriceEnding::ofDecimals('1', '0', Currency::of('CAD'), RoundingMode::NegativeInfinity));
    }

    public function testAnEndingToOneUnitIsKept(): void
    {
        $suggested = SuggestedRetailPrice::fromSupplier($this->cad(50))->ended(PriceEnding::ofDecimals('0.10', '0.01', Currency::of('CAD'), RoundingMode::NegativeInfinity));

        self::assertSame(41, $suggested->price->minorUnits);
        self::assertSame(1, SuggestedRetailPrice::fromSupplier($this->cad(5))->ended(PriceEnding::ofDecimals('0.10', '0.01', Currency::of('CAD'), RoundingMode::NegativeInfinity))->price->minorUnits);
    }

    public function testAnEndingInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        SuggestedRetailPrice::fromCost($this->cad(600), Rate::ofPercent('60'))->ended(PriceEnding::ofDecimals('1', '0.99', Currency::of('USD')));
    }

    public function testComparingGivesTheComparisonToTheSuggestedPrice(): void
    {
        $comparison = SuggestedRetailPrice::fromSupplier($this->cad(2_500))->compare($this->cad(2_000));

        self::assertInstanceOf(RetailPriceComparison::class, $comparison);
        self::assertSame(2_000, $comparison->sellingPrice->minorUnits);
        self::assertSame(2_500, $comparison->suggestedPrice->minorUnits);
    }

    public function testComparingUsesTheEndedPrice(): void
    {
        $comparison = SuggestedRetailPrice::fromCost($this->cad(600), Rate::ofPercent('60'))->ended($this->ninetyNine())->compare($this->cad(999));

        self::assertTrue($comparison->isAtSuggestedPrice());
    }
}
