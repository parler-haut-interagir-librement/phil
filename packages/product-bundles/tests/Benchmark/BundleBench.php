<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Benchmark;

use LogicException;
use Phil\Money\Currency;
use Phil\Pricing\Rate;
use Phil\ProductBundles\BundleAvailability;
use Phil\ProductBundles\BundleComponent;
use Phil\ProductBundles\BundleKind;
use Phil\ProductBundles\BundleMargin;
use Phil\ProductBundles\BundlePricing;
use Phil\ProductBundles\Bundles;
use Phil\ProductBundles\ComposedCost;
use Phil\ProductBundles\Memory\InMemoryBundles;
use Phil\ProductBundles\PricePolicy;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\SoldBundle;
use Phil\ProductBundles\Tests\Support\Shop;

/**
 * The hot paths of a catalogue page and a checkout: a bundle of the most components quoted, its stock and its margin
 * read, a sale frozen, and its stored line read back.
 */
final readonly class BundleBench
{
    private Bundles $bundles;

    /** @var array<string, mixed> */
    private array $line;

    public function __construct()
    {
        $prices = [];
        $stock = [];
        $costs = [];
        $components = [];

        for ($i = 1; $i <= 50; ++$i) {
            $prices['C'.$i] = 1000 + $i;
            $stock['C'.$i] = 500 + $i;
            $costs['C'.$i] = 400 + $i;
            $components[] = new BundleComponent('C'.$i, 1 + $i % 3);
        }

        $shop = new Shop($prices, $stock, $costs);
        $store = new InMemoryBundles();
        $store->save(new ProductBundle('BIG', BundleKind::Collection, Currency::of('CAD'), $components, PricePolicy::discountOnComponents(Rate::ofPercent('12.5'))));

        $this->bundles = new Bundles($store, new BundlePricing($shop), new BundleAvailability($shop), new ComposedCost($shop));
        $this->line = $this->bundles->sell('BIG', 10)->toArray();
    }

    public function benchQuoteOfFiftyComponents(): void
    {
        if (50 !== \count($this->bundles->quote('BIG')->components)) {
            throw new LogicException('The quote has every component.');
        }
    }

    public function benchStockAndMargin(): void
    {
        if (0 === $this->bundles->stock('BIG')->quantity || !$this->bundles->margin('BIG') instanceof BundleMargin) {
            throw new LogicException('The bundle has stock and a margin.');
        }
    }

    public function benchSaleFrozen(): void
    {
        if (10 !== $this->bundles->sell('BIG', 10)->quantity) {
            throw new LogicException('The sale is frozen.');
        }
    }

    public function benchStoredLineReadBack(): void
    {
        if ('BIG' !== SoldBundle::fromArray($this->line)->sku) {
            throw new LogicException('The line is read back.');
        }
    }
}
