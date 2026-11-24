<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\Rate;
use Phil\Pricing\RetailPriceComparison;
use Phil\Pricing\SuggestedRetailPrice;
use Phil\Pricing\WholesalePriceList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Every example of doc/suggested-retail-prices.md, and the suggested price of the README's quick start, with the output
 * they show.
 */
#[CoversClass(SuggestedRetailPrice::class)]
#[CoversClass(RetailPriceComparison::class)]
#[CoversClass(WholesalePriceList::class)]
final class SuggestedRetailPriceDocumentationTest extends TestCase
{
    private Currency $cad;

    private Money $cost;

    protected function setUp(): void
    {
        $this->cad = Currency::of('CAD');
        $this->cost = Money::ofDecimal('6.00', $this->cad);
    }

    public function testTheSources(): void
    {
        self::assertSame('24.99', SuggestedRetailPrice::fromSupplier(Money::ofDecimal('24.99', $this->cad))->price->toDecimal());
        self::assertSame('9.60', SuggestedRetailPrice::fromCost($this->cost, Rate::ofPercent('60'))->price->toDecimal());
        self::assertSame('12.00', SuggestedRetailPrice::keystone($this->cost)->price->toDecimal());
        self::assertTrue(SuggestedRetailPrice::keystone($this->cost)->isKeystone());
        self::assertSame('15.60', SuggestedRetailPrice::fromWholesale(Money::ofDecimal('7.80', $this->cad), Rate::ofPercent('100'))->price->toDecimal());
        self::assertSame('8.60', SuggestedRetailPrice::fromCost(Money::ofDecimal('6.37', $this->cad), Rate::ofPercent('35'))->price->toDecimal());
    }

    public function testTheEnding(): void
    {
        $suggested = SuggestedRetailPrice::fromWholesale(Money::ofDecimal('7.80', $this->cad), Rate::ofPercent('100'))
            ->ended(PriceEnding::ofDecimals('1', '0.99', $this->cad));

        self::assertSame('15.99', $suggested->price->toDecimal());
        self::assertSame('15.60', $suggested->unendedPrice->toDecimal());
    }

    public function testTheComparison(): void
    {
        $comparison = SuggestedRetailPrice::fromSupplier(Money::ofDecimal('25.00', $this->cad))
            ->compare(Money::ofDecimal('20.00', $this->cad));

        self::assertSame('-5.00', $comparison->difference()->toDecimal());
        self::assertSame('-20', $comparison->rate()->percent());
        self::assertSame('20', $comparison->rateBelow()->percent());
        self::assertTrue($comparison->isBelow());
        self::assertSame('-20.0125', RetailPriceComparison::of(Money::ofDecimal('12.79', $this->cad), Money::ofDecimal('15.99', $this->cad))->rate()->percent());
    }

    public function testTheQuickStartOfTheReadme(): void
    {
        $suggested = SuggestedRetailPrice::keystone($this->cost)->ended(PriceEnding::ofDecimals('1', '0.99', $this->cad));

        self::assertSame('12.99', $suggested->price->toDecimal());
        self::assertSame('44.6497', $suggested->compare(Money::ofDecimal('7.19', $this->cad))->rateBelow()->percent());
    }

    public function testTheWholesaleTiers(): void
    {
        $wholesale = WholesalePriceList::fromCost($this->cost, Rate::ofPercent('30'))
            ->fromMarkup(12, Rate::ofPercent('25'))
            ->from(48, Money::ofDecimal('7.20', $this->cad));

        self::assertSame('7.80', $wholesale->basePrice()->toDecimal());
        self::assertSame('7.80', $wholesale->unitPriceFor(11)->toDecimal());
        self::assertSame('7.50', $wholesale->unitPriceFor(12)->toDecimal());
        self::assertSame('7.50', $wholesale->unitPriceFor(47)->toDecimal());
        self::assertSame('7.20', $wholesale->unitPriceFor(48)->toDecimal());
        self::assertSame('180.00', $wholesale->totalFor(24)->toDecimal());
        self::assertSame('15.60', SuggestedRetailPrice::fromWholesale($wholesale->basePrice(), Rate::ofPercent('100'))->price->toDecimal());
    }
}
