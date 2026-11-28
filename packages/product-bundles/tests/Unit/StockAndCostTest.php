<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductBundles\BundleAvailability;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleCost;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundleMargin;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\BundleQuote;
use Phil\ProductBundles\BundleStock;
use Phil\ProductBundles\ComponentCost;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\Tests\Support\Shop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(BundleAvailability::class)]
#[CoversClass(BundleStock::class)]
#[CoversClass(ComposedCost::class)]
#[CoversClass(BundleCost::class)]
#[CoversClass(ComponentCost::class)]
#[CoversClass(BundleMargin::class)]
final class StockAndCostTest extends TestCase
{
    public function testTheScarcestComponentSetsTheStock(): void
    {
        $stock = new BundleAvailability(new Shop(stock: ['DECK' => 11, 'BOOK' => 20]))->of($this->bundle());

        self::assertSame('STARTER', $stock->sku);
        self::assertSame(5, $stock->quantity);
        self::assertSame('DECK', $stock->limitedBy);
        self::assertTrue($stock->isAvailable());
    }

    public function testTheFirstOfEqualLimitsAndNoStockBelowZero(): void
    {
        $availability = new BundleAvailability(new Shop(stock: ['BOOK' => 4, 'DECK' => 8]));
        $stock = $availability->of($this->bundle());
        self::assertSame(4, $stock->quantity);
        self::assertSame('BOOK', $stock->limitedBy);

        $stock = new BundleAvailability(new Shop(stock: ['BOOK' => -3, 'DECK' => 1]))->of($this->bundle());
        self::assertSame(0, $stock->quantity);
        self::assertSame('BOOK', $stock->limitedBy);
        self::assertFalse($stock->isAvailable());

        $stock = new BundleAvailability(new Shop(stock: ['BOOK' => 3, 'DECK' => 1]))->of($this->bundle());
        self::assertSame(0, $stock->quantity);
        self::assertSame('DECK', $stock->limitedBy);
    }

    public function testTheCostIsComposedFromTheComponents(): void
    {
        $cost = new ComposedCost(new Shop(costs: ['BOOK' => 1800, 'DECK' => 250]))->of($this->bundle());

        self::assertTrue($cost->isKnown());
        self::assertSame('STARTER', $cost->sku);
        self::assertSame(2300, $cost->unitCost?->minorUnits);
        self::assertSame([], $cost->unknown);
        self::assertSame([['BOOK', 1, 1800, 1800], ['DECK', 2, 250, 500]], array_map(
            static fn (ComponentCost $component): array => [$component->sku, $component->quantity, $component->unitCost?->minorUnits, $component->cost?->minorUnits],
            $cost->components,
        ));
    }

    public function testOneUnknownCostLeavesTheCostUnknown(): void
    {
        $cost = new ComposedCost(new Shop(costs: ['DECK' => 250]))->of($this->bundle());

        self::assertFalse($cost->isKnown());
        self::assertNull($cost->unitCost);
        self::assertSame(['BOOK'], $cost->unknown);
        self::assertNull($cost->components[0]->cost);
        self::assertSame(500, $cost->components[1]->cost?->minorUnits);
    }

    public function testTheMarginIsThePriceLessTheComposedCost(): void
    {
        $shop = new Shop(['BOOK' => 4000, 'DECK' => 500], costs: ['BOOK' => 1800, 'DECK' => 250]);
        $costs = new ComposedCost($shop);
        $quote = new BundlePricing($shop)->quote($this->bundle());

        $margin = $costs->margin($quote, $costs->of($this->bundle()));
        self::assertInstanceOf(BundleMargin::class, $margin);
        self::assertSame('STARTER', $margin->sku);
        self::assertSame(4500, $margin->price->minorUnits);
        self::assertSame(2300, $margin->cost->minorUnits);
        self::assertSame(2200, $margin->margin->minorUnits);
        self::assertSame(488_889, $margin->rate?->partsPerMillion);
        self::assertSame(488_888, $costs->margin($quote, $costs->of($this->bundle()), RoundingMode::TowardsZero)?->rate?->partsPerMillion);
    }

    public function testNoMarginWithoutACostOrInAnotherCurrencyAndNoRateOnAFreeBundle(): void
    {
        $shop = new Shop(['BOOK' => 4000, 'DECK' => 500], costs: ['DECK' => 250]);
        $costs = new ComposedCost($shop);
        $quote = new BundlePricing($shop)->quote($this->bundle());

        self::assertNull($costs->margin($quote, $costs->of($this->bundle())));
        self::assertNull($costs->margin($quote, new BundleCost('STARTER', Money::of(100, Currency::of('USD')), [], [])));

        $free = new BundleQuote('STARTER', Shop::cad(0), Shop::cad(0), []);
        $margin = $costs->margin($free, new BundleCost('STARTER', Shop::cad(100), [], []));
        self::assertInstanceOf(BundleMargin::class, $margin);
        self::assertSame(-100, $margin->margin->minorUnits);
        self::assertNull($margin->rate);
    }

    private function bundle(): ProductBundle
    {
        return new ProductBundle('STARTER', BundleKind::Assembled, Currency::of('CAD'), [new BundleComponent('BOOK'), new BundleComponent('DECK', 2)], PricePolicy::fixed(Shop::cad(4500)));
    }
}
