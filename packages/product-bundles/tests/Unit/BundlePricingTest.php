<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\Rate;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\BundleQuote;
use Phil\ProductBundles\Contract\ComponentPrices;
use Phil\ProductBundles\Exception\PriceUnknown;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\QuotedComponent;
use Phil\ProductBundles\Tests\Support\Shop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BundlePricing::class)]
#[CoversClass(BundleQuote::class)]
#[CoversClass(QuotedComponent::class)]
#[CoversClass(PriceUnknown::class)]
final class BundlePricingTest extends TestCase
{
    public function testAFixedPriceIsSharedByWhatTheComponentsSellForApart(): void
    {
        $quote = new BundlePricing(new Shop(['BOOK' => 4000, 'DICE' => 500]))->quote($this->bundle(PricePolicy::fixed(Shop::cad(4500))));

        self::assertSame('STARTER', $quote->sku);
        self::assertSame(5000, $quote->componentsValue->minorUnits);
        self::assertSame(4500, $quote->price->minorUnits);
        self::assertSame(500, $quote->savings()->minorUnits);
        self::assertSame([['BOOK', 1, 4000, 4000, 3600], ['DICE', 2, 500, 1000, 900]], array_map(
            static fn (QuotedComponent $component): array => [$component->sku, $component->quantity, $component->unitPrice->minorUnits, $component->value->minorUnits, $component->share->minorUnits],
            $quote->components,
        ));
    }

    public function testADiscountTakesARateOffTheComponents(): void
    {
        $quote = new BundlePricing(new Shop(['BOOK' => 3333, 'DICE' => 1]))->quote($this->bundle(PricePolicy::discountOnComponents(Rate::ofPercent('10'))));

        self::assertSame(3335, $quote->componentsValue->minorUnits);
        self::assertSame(3001, $quote->price->minorUnits);
        self::assertSame([2999, 2], array_map(static fn (QuotedComponent $component): int => $component->share->minorUnits, $quote->components));
    }

    public function testABundleAboveItsComponentsSavesNothing(): void
    {
        $quote = new BundlePricing(new Shop(['BOOK' => 1000, 'DICE' => 0]))->quote($this->bundle(PricePolicy::fixed(Shop::cad(1200))));

        self::assertSame(-200, $quote->savings()->minorUnits);
        self::assertSame([1200, 0], array_map(static fn (QuotedComponent $component): int => $component->share->minorUnits, $quote->components));
    }

    public function testFreeComponentsShareByQuantity(): void
    {
        $quote = new BundlePricing(new Shop(['BOOK' => 0, 'DICE' => 0]))->quote($this->bundle(PricePolicy::fixed(Shop::cad(300))));

        self::assertSame([100, 200], array_map(static fn (QuotedComponent $component): int => $component->share->minorUnits, $quote->components));
    }

    public function testAMissingForeignOrNegativePriceLeavesTheBundleUnpriced(): void
    {
        $pricing = new BundlePricing(new Shop(['BOOK' => 4000]));

        try {
            $pricing->quote($this->bundle(PricePolicy::fixed(Shop::cad(4500))));
            self::fail('A missing price is refused.');
        } catch (PriceUnknown $priceUnknown) {
            self::assertSame('The component "DICE" of the bundle "STARTER" has no price in CAD.', $priceUnknown->getMessage());
        }

        $usd = new ProductBundle('STARTER', BundleKind::Assembled, Currency::of('USD'), [new BundleComponent('BOOK')], PricePolicy::discountOnComponents(Rate::zero()));
        $this->expectExceptionObject(PriceUnknown::of('STARTER', 'BOOK', 'USD'));
        $pricing->quote($usd);
    }

    public function testAPriceInAnotherCurrencyOrBelowZeroIsRefused(): void
    {
        $prices = new class implements ComponentPrices {
            public function unitPrice(string $sku, Currency $currency): Money
            {
                return 'BOOK' === $sku ? Money::of(100, Currency::of('USD')) : Money::of(-1, $currency);
            }
        };
        $pricing = new BundlePricing($prices);
        $bundle = static fn (string $sku): ProductBundle => new ProductBundle('B', BundleKind::Assembled, Currency::of('CAD'), [new BundleComponent($sku)], PricePolicy::discountOnComponents(Rate::zero()));

        foreach (['BOOK', 'DICE'] as $sku) {
            try {
                $pricing->quote($bundle($sku));
                self::fail('The price is refused.');
            } catch (PriceUnknown $unknown) {
                self::assertSame(\sprintf('The component "%s" of the bundle "B" has no price in CAD.', $sku), $unknown->getMessage());
            }
        }
    }

    private function bundle(PricePolicy $price): ProductBundle
    {
        return new ProductBundle('STARTER', BundleKind::Assembled, Currency::of('CAD'), [new BundleComponent('BOOK'), new BundleComponent('DICE', 2)], $price);
    }
}
