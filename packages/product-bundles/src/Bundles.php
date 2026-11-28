<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\ProductBundles\Contract\BundleRepository;
use Phil\ProductBundles\Exception\PriceUnknown;
use Phil\ProductBundles\Exception\SaleRefused;
use Phil\ProductBundles\Exception\UnknownBundle;
use Phil\ProductBundles\Internal\Check;

/**
 * The bundles of a shop by SKU: their price, stock, cost and margin now, and the line a sale freezes.
 *
 * Stateless between calls: every answer reads the ports again, so a worker serves request after request.
 */
final readonly class Bundles
{
    public function __construct(
        private BundleRepository $bundles,
        private BundlePricing $pricing,
        private BundleAvailability $availability,
        private ComposedCost $costs,
    ) {
    }

    /**
     * @throws UnknownBundle
     */
    public function get(string $sku): ProductBundle
    {
        return $this->bundles->find($sku) ?? throw UnknownBundle::sku($sku);
    }

    /**
     * @throws UnknownBundle
     * @throws PriceUnknown
     */
    public function quote(string $sku): BundleQuote
    {
        return $this->pricing->quote($this->get($sku));
    }

    /**
     * @throws UnknownBundle
     */
    public function stock(string $sku): BundleStock
    {
        return $this->availability->of($this->get($sku));
    }

    /**
     * @throws UnknownBundle
     */
    public function cost(string $sku): BundleCost
    {
        return $this->costs->of($this->get($sku));
    }

    /**
     * The margin at today's price and cost; null when the cost is unknown.
     *
     * @throws UnknownBundle
     * @throws PriceUnknown
     */
    public function margin(string $sku): ?BundleMargin
    {
        $bundle = $this->get($sku);

        return $this->costs->margin($this->pricing->quote($bundle), $this->costs->of($bundle));
    }

    /**
     * The line of a sale of some bundles, priced and costed now; refused when the components cannot fill it, unless
     * the shop sells on back order.
     *
     * @throws UnknownBundle
     * @throws PriceUnknown
     * @throws SaleRefused
     */
    public function sell(string $sku, int $quantity, bool $backorder = false): SoldBundle
    {
        Check::between('quantity sold', $quantity, 1, Check::MAX_QUANTITY);
        $bundle = $this->get($sku);

        if (!$backorder) {
            $stock = $this->availability->of($bundle);

            if ($stock->quantity < $quantity) {
                throw SaleRefused::outOfStock($sku, $quantity, $stock->quantity, $stock->limitedBy);
            }
        }

        return SoldBundle::of($bundle, $this->pricing->quote($bundle), $quantity, $this->costs->of($bundle));
    }
}
