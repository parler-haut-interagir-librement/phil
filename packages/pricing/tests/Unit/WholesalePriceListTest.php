<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Money;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\MarkedUpPrice;
use Phil\Pricing\Rate;
use Phil\Pricing\WholesalePriceList;
use Phil\Pricing\WholesaleTier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(WholesalePriceList::class)]
#[CoversClass(WholesaleTier::class)]
#[CoversClass(MarkedUpPrice::class)]
#[CoversClass(InvalidPricing::class)]
#[CoversClass(InvalidRate::class)]
final class WholesalePriceListTest extends TestCase
{
    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    private function tiered(): WholesalePriceList
    {
        return WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))
            ->fromMarkup(12, Rate::ofPercent('25'))
            ->from(48, $this->cad(720));
    }

    public function testTheBaseTierIsTheCostMarkedUpFromOneUnit(): void
    {
        $list = WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'));

        self::assertSame(780, $list->basePrice()->minorUnits);
        self::assertSame(600, $list->cost->minorUnits);
        self::assertSame(RoundingMode::AwayFromZero, $list->mode);
        self::assertCount(1, $list->tiers);
        self::assertSame(1, $list->tiers[0]->minimumQuantity);
        self::assertSame(780, $list->tiers[0]->unitPrice->minorUnits);
        self::assertSame(300_000, $list->tiers[0]->markup?->partsPerMillion);
    }

    public function testASingleTierPricesEveryQuantity(): void
    {
        $list = WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'));

        self::assertSame(780, $list->unitPriceFor(1)->minorUnits);
        self::assertSame(780, $list->unitPriceFor(1_000)->minorUnits);
        self::assertSame(1, $list->tierFor(1_000)->minimumQuantity);
    }

    public function testTheBasePriceRoundsAwayFromZeroOrByTheModeGiven(): void
    {
        self::assertSame(860, WholesalePriceList::fromCost($this->cad(637), Rate::ofPercent('35'))->basePrice()->minorUnits);

        $towardsZero = WholesalePriceList::fromCost($this->cad(637), Rate::ofPercent('35'), RoundingMode::TowardsZero);

        self::assertSame(859, $towardsZero->basePrice()->minorUnits);
        self::assertSame(RoundingMode::TowardsZero, $towardsZero->mode);
        self::assertSame(796, $towardsZero->fromMarkup(10, Rate::ofPercent('25'))->unitPriceFor(10)->minorUnits);
    }

    /**
     * Quantity, unit price, tier reached.
     *
     * @return iterable<string, array{int, int, int}>
     */
    public static function quantities(): iterable
    {
        yield 'one unit' => [1, 780, 1];
        yield 'below the first tier' => [11, 780, 1];
        yield 'at the first tier' => [12, 750, 12];
        yield 'between the tiers' => [47, 750, 12];
        yield 'at the second tier' => [48, 720, 48];
        yield 'beyond the last tier' => [1_000, 720, 48];
    }

    #[DataProvider('quantities')]
    public function testAQuantityReachesTheLastTierItMeets(int $quantity, int $unitPrice, int $tier): void
    {
        self::assertSame($unitPrice, $this->tiered()->unitPriceFor($quantity)->minorUnits);
        self::assertSame($tier, $this->tiered()->tierFor($quantity)->minimumQuantity);
        self::assertSame($unitPrice * $quantity, $this->tiered()->totalFor($quantity)->minorUnits);
    }

    public function testTheTiersKeepTheirPricesAndMarkups(): void
    {
        $tiers = $this->tiered()->tiers;

        self::assertCount(3, $tiers);
        self::assertSame(250_000, $tiers[1]->markup?->partsPerMillion);
        self::assertSame(750, $tiers[1]->unitPrice->minorUnits);
        self::assertNull($tiers[2]->markup);
        self::assertSame(720, $tiers[2]->unitPrice->minorUnits);
        self::assertSame(780, $this->tiered()->basePrice()->minorUnits);
    }

    public function testATotalIsTheUnitPriceOfItsTierTimesTheQuantity(): void
    {
        self::assertSame(18_000, $this->tiered()->totalFor(24)->minorUnits);
        self::assertSame(780, $this->tiered()->totalFor(1)->minorUnits);
    }

    public function testAddingATierLeavesTheListUntouched(): void
    {
        $list = WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'));
        $list->from(12, $this->cad(750));

        self::assertCount(1, $list->tiers);
    }

    public function testATierAtTheSamePriceIsAccepted(): void
    {
        $list = WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from(12, $this->cad(780));

        self::assertSame(780, $list->unitPriceFor(12)->minorUnits);
        self::assertSame(12, $list->tierFor(12)->minimumQuantity);
    }

    public function testATierFromTwoUnitsIsTheSmallestAccepted(): void
    {
        self::assertSame(750, WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from(2, $this->cad(750))->unitPriceFor(2)->minorUnits);
    }

    public function testATierThatRaisesThePriceIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('The tier from 48 units costs 7.51 a unit, more than the 7.50 of the tier before it: a larger quantity never raises the unit price.');

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from(12, $this->cad(750))->from(48, $this->cad(751));
    }

    public function testATierFromAHigherMarkupIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->fromMarkup(12, Rate::ofPercent('31'));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function quantitiesOutOfOrder(): iterable
    {
        yield 'the same as the previous tier' => [12, 12];
        yield 'below the previous tier' => [12, 6];
    }

    #[DataProvider('quantitiesOutOfOrder')]
    public function testATierNotAboveThePreviousOneIsRefused(int $previous, int $quantity): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The tier from %d units does not start above the tier from %d units: tiers are added in ascending order.', $quantity, $previous));

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from($previous, $this->cad(750))->from($quantity, $this->cad(700));
    }

    public function testATierFromOneUnitIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('The tier from 1 units does not start above the tier from 1 units');

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from(1, $this->cad(750));
    }

    public function testAMarkupTierOutOfOrderIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->fromMarkup(12, Rate::ofPercent('25'))->fromMarkup(12, Rate::ofPercent('20'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unitPricesNotAboveZero(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-750];
    }

    #[DataProvider('unitPricesNotAboveZero')]
    public function testATierPriceNotAboveZeroIsRefused(int $unitPrice): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A wholesale unit price is above zero, '.$this->cad($unitPrice)->toDecimal().' is not.');

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from(12, $this->cad($unitPrice));
    }

    public function testATierPriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->from(12, Money::of(-750, Currency::of('USD')));
    }

    public function testAZeroCostIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A suggested or wholesale price marks up a cost: a zero cost gives none.');

        WholesalePriceList::fromCost($this->cad(0), Rate::ofPercent('30'));
    }

    public function testANegativeCostIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('-6.00 is negative');

        WholesalePriceList::fromCost($this->cad(-600), Rate::ofPercent('30'));
    }

    public function testAMarkupOfMinusOneHundredPercentIsRefused(): void
    {
        $this->expectException(InvalidRate::class);

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('-100'));
    }

    public function testATierMarkupOfMinusOneHundredPercentIsRefused(): void
    {
        $this->expectException(InvalidRate::class);

        WholesalePriceList::fromCost($this->cad(600), Rate::ofPercent('30'))->fromMarkup(12, Rate::ofPercent('-100'));
    }

    public function testABasePriceThatRoundsToZeroIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A wholesale unit price is above zero, 0.00 is not.');

        WholesalePriceList::fromCost($this->cad(1), Rate::of(-999_999), RoundingMode::TowardsZero);
    }

    public function testATierPriceThatRoundsToZeroIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains('A wholesale unit price is above zero, 0.00 is not.');

        WholesalePriceList::fromCost($this->cad(1), Rate::zero(), RoundingMode::TowardsZero)->fromMarkup(2, Rate::of(-999_999));
    }

    public function testTheSmallestBasePriceIsOneUnit(): void
    {
        self::assertSame(1, WholesalePriceList::fromCost($this->cad(1), Rate::of(-999_999))->basePrice()->minorUnits);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function quantitiesBelowOne(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('quantitiesBelowOne')]
    public function testAQuantityBelowOneIsRefused(int $quantity): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A wholesale quantity is 1 or more, %d is not.', $quantity));

        $this->tiered()->unitPriceFor($quantity);
    }

    public function testATotalOfAQuantityBelowOneIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);

        $this->tiered()->totalFor(0);
    }

    public function testACostTooLargeToMarkUpIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        WholesalePriceList::fromCost($this->cad(\PHP_INT_MAX), Rate::ofPercent('30'));
    }

    public function testATotalBeyondTheNativeRangeIsRefused(): void
    {
        $this->expectException(InvalidAmount::class);

        $this->tiered()->totalFor(\PHP_INT_MAX);
    }
}
