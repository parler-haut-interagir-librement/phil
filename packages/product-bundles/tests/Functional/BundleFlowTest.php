<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Functional;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Pricing\Rate;
use Phil\ProductBundles\BundleAvailability;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\Bundles;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\Cooperative\CooperativeBundle;
use Phil\ProductBundles\Cooperative\CooperativeShare;
use Phil\ProductBundles\Memory\InMemoryBundles;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\SoldBundle;
use Phil\ProductBundles\StockDraw;
use Phil\ProductBundles\Tests\Support\Shop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A starter set from its definition to its return: the bundle saved, its price, stock, cost and margin read, a sale
 * frozen on its line and its stock drawn, the line stored and read back, a whole bundle returned and put back; then a
 * cooperative bundle of three creators approved and its first sale shared out.
 */
#[CoversClass(Bundles::class)]
#[CoversClass(SoldBundle::class)]
#[CoversClass(CooperativeBundle::class)]
final class BundleFlowTest extends TestCase
{
    public function testAStarterSetIsSoldAndReturnedWhole(): void
    {
        $shop = new Shop(['RULES' => 3999, 'DICE' => 1299, 'SCREEN' => 1999], ['RULES' => 12, 'DICE' => 7, 'SCREEN' => 30], ['RULES' => 2100, 'DICE' => 420, 'SCREEN' => 900]);
        $store = new InMemoryBundles();
        $store->save(new ProductBundle('STARTER', BundleKind::Assembled, Currency::of('CAD'), [
            new BundleComponent('RULES'),
            new BundleComponent('DICE', 2),
            new BundleComponent('SCREEN'),
        ], PricePolicy::discountOnComponents(Rate::ofPercent('15'))));
        $bundles = new Bundles($store, new BundlePricing($shop), new BundleAvailability($shop), new ComposedCost($shop));

        $quote = $bundles->quote('STARTER');
        self::assertSame(8596, $quote->componentsValue->minorUnits);
        self::assertSame(7307, $quote->price->minorUnits);
        self::assertSame(1289, $quote->savings()->minorUnits);
        self::assertSame(3, $bundles->stock('STARTER')->quantity);
        self::assertSame('DICE', $bundles->stock('STARTER')->limitedBy);
        self::assertSame(3840, $bundles->cost('STARTER')->unitCost?->minorUnits);
        self::assertSame(3467, $bundles->margin('STARTER')?->margin->minorUnits);

        $sold = $bundles->sell('STARTER', 2);
        foreach ($sold->draws() as $draw) {
            $shop->stock[$draw->sku] -= $draw->units;
        }

        self::assertSame(['RULES' => 10, 'DICE' => 3, 'SCREEN' => 28], $shop->stock);
        self::assertSame(1, $bundles->stock('STARTER')->quantity);

        $stored = json_decode(json_encode($sold->toArray(), \JSON_THROW_ON_ERROR), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($stored);
        $line = SoldBundle::fromArray($stored);
        $refund = $line->refund(1);
        self::assertSame(7307, $refund->amount->minorUnits);
        self::assertSame([['RULES', 1], ['DICE', 2], ['SCREEN', 1]], array_map(static fn (StockDraw $draw): array => [$draw->sku, $draw->units], $refund->restock));
        self::assertSame(1, $refund->after->returnable());
    }

    public function testACooperativeBundleIsApprovedAndItsSaleSharedOut(): void
    {
        $bundle = CooperativeBundle::propose('ZINE-PACK', 'creator-a', ['creator-a' => 2, 'creator-b' => 1, 'creator-c' => 1], Rate::ofPercent('5'));

        foreach (['creator-b', 'creator-a', 'creator-c'] as $hour => $creator) {
            $bundle = $bundle->approve($creator, new DateTimeImmutable(\sprintf('2026-11-24 %02d:00:00 UTC', 10 + $hour)));
        }

        $bundle = CooperativeBundle::fromArray($bundle->toArray());
        self::assertTrue($bundle->isOnSale());
        self::assertSame('2026-11-24 10:00', $bundle->lockedAt?->format('Y-m-d H:i'));
        self::assertSame([[null, 100], ['creator-a', 950], ['creator-b', 475], ['creator-c', 475]], array_map(
            static fn (CooperativeShare $share): array => [$share->participant, $share->amount->minorUnits],
            $bundle->shares(Shop::cad(2000)),
        ));
    }
}
