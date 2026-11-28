<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Support;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductBundles\Contract\ComponentCosts;
use Phil\ProductBundles\Contract\ComponentPrices;
use Phil\ProductBundles\Contract\ComponentStock;

/**
 * A shop's catalogue, stock and costs in arrays: prices in Canadian cents, units available, unit costs.
 */
final class Shop implements ComponentPrices, ComponentStock, ComponentCosts
{
    /**
     * @param array<string, int> $prices in cents of CAD
     * @param array<string, int> $stock
     * @param array<string, int> $costs  in cents of CAD
     */
    public function __construct(
        public array $prices = [],
        public array $stock = [],
        public array $costs = [],
    ) {
    }

    public static function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }

    public function unitPrice(string $sku, Currency $currency): ?Money
    {
        return isset($this->prices[$sku]) && 'CAD' === $currency->code ? self::cad($this->prices[$sku]) : null;
    }

    public function available(string $sku): int
    {
        return $this->stock[$sku] ?? 0;
    }

    public function unitCost(string $sku): ?Money
    {
        return isset($this->costs[$sku]) ? self::cad($this->costs[$sku]) : null;
    }
}
