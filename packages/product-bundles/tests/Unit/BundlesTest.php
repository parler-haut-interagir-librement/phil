<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit;

use Phil\Money\Currency;
use Phil\ProductBundles\BundleAvailability;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\Bundles;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Exception\SaleRefused;
use Phil\ProductBundles\Exception\UnknownBundle;
use Phil\ProductBundles\Memory\InMemoryBundles;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\Tests\Support\Shop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bundles::class)]
#[CoversClass(InMemoryBundles::class)]
#[CoversClass(UnknownBundle::class)]
#[CoversClass(SaleRefused::class)]
final class BundlesTest extends TestCase
{
    private Bundles $bundles;

    private InMemoryBundles $store;

    protected function setUp(): void
    {
        $shop = new Shop(['BOOK' => 4000, 'DECK' => 500], ['BOOK' => 3, 'DECK' => 4], ['BOOK' => 1800, 'DECK' => 250]);
        $this->store = new InMemoryBundles();
        $this->store->save(new ProductBundle('STARTER', BundleKind::Assembled, Currency::of('CAD'), [new BundleComponent('BOOK'), new BundleComponent('DECK', 2)], PricePolicy::fixed(Shop::cad(4500))));

        $this->bundles = new Bundles($this->store, new BundlePricing($shop), new BundleAvailability($shop), new ComposedCost($shop));
    }

    public function testTheBundleIsReadByItsSku(): void
    {
        self::assertSame('STARTER', $this->bundles->get('STARTER')->sku);
        self::assertSame(4500, $this->bundles->quote('STARTER')->price->minorUnits);
        self::assertSame(2, $this->bundles->stock('STARTER')->quantity);
        self::assertSame(2300, $this->bundles->cost('STARTER')->unitCost?->minorUnits);
        self::assertSame(2200, $this->bundles->margin('STARTER')?->margin->minorUnits);

        $this->expectExceptionObject(UnknownBundle::sku('NONE'));
        $this->bundles->get('NONE');
    }

    public function testASaleWithinTheStockIsFrozen(): void
    {
        $sold = $this->bundles->sell('STARTER', 2);

        self::assertSame(2, $sold->quantity);
        self::assertSame(2300, $sold->unitCost()?->minorUnits);
        self::assertSame(9000, $sold->total()->minorUnits);
    }

    public function testASaleBeyondTheStockIsRefusedUnlessOnBackOrder(): void
    {
        self::assertSame(3, $this->bundles->sell('STARTER', 3, backorder: true)->quantity);

        $this->expectExceptionObject(SaleRefused::outOfStock('STARTER', 3, 2, 'DECK'));
        $this->bundles->sell('STARTER', 3);
    }

    public function testTheMessageOfARefusedSale(): void
    {
        self::assertSame('The bundle "STARTER" has 2 in stock, limited by "DECK"; 3 asked.', SaleRefused::outOfStock('STARTER', 3, 2, 'DECK')->getMessage());
        self::assertSame('No bundle has the SKU "NONE".', UnknownBundle::sku('NONE')->getMessage());
    }

    public function testTheQuantityOfASaleIsBounded(): void
    {
        $this->expectExceptionObject(InvalidBundle::because('quantity sold', 'is from 1 to 1000, not 0'));
        $this->bundles->sell('STARTER', 0, backorder: true);
    }

    public function testTheQuantityIsCheckedBeforeTheBundleIsLooked(): void
    {
        $this->expectExceptionObject(InvalidBundle::because('quantity sold', 'is from 1 to 1000, not 0'));
        $this->bundles->sell('MISSING', 0);
    }

    public function testTheStoreListsAHundredBundlesByDefault(): void
    {
        for ($index = 0; $index < 101; ++$index) {
            $this->store->save(new ProductBundle(\sprintf('B%03d', $index), BundleKind::Collection, Currency::of('CAD'), [new BundleComponent('MAP')], PricePolicy::fixed(Shop::cad(500))));
        }

        self::assertCount(100, $this->store->containing('MAP'));
        self::assertCount(101, $this->store->containing('MAP', 1000));
    }

    public function testTheStoreFindsTheBundlesOfAComponent(): void
    {
        $this->store->save(new ProductBundle('42', BundleKind::Collection, Currency::of('CAD'), [new BundleComponent('DECK')], PricePolicy::fixed(Shop::cad(500))));
        $this->store->save(new ProductBundle('ALPHA', BundleKind::Collection, Currency::of('CAD'), [new BundleComponent('DECK')], PricePolicy::fixed(Shop::cad(500))));

        self::assertSame(['42', 'ALPHA', 'STARTER'], $this->store->containing('DECK'));
        self::assertSame(['42'], $this->store->containing('DECK', 1));
        self::assertSame(['STARTER'], $this->store->containing('BOOK', 1000));
        self::assertSame([], $this->store->containing('MAP'));

        $this->store->remove('STARTER');
        self::assertNull($this->store->find('STARTER'));
        self::assertSame(['42', 'ALPHA'], $this->store->containing('DECK'));

        foreach ([0, 1001] as $limit) {
            try {
                $this->store->containing('DECK', $limit);
                self::fail('The limit is bounded.');
            } catch (InvalidBundle $refused) {
                self::assertSame(\sprintf('The limit is from 1 to 1000, not %d.', $limit), $refused->getMessage());
            }
        }
    }
}
