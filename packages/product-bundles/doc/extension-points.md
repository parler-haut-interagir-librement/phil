# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Contract\BundleRepository` | a table, with what `ProductBundle::toArray()` gives | keeps the bundles by SKU; finds the bundles that hold a component |
| `Contract\ComponentPrices` | an adapter to the catalogue | gives a SKU's unit price in a currency, or null |
| `Contract\ComponentStock` | an adapter to the logistics | gives a SKU's available units |
| `Contract\ComponentCosts` | an adapter to the stock valuation | gives a SKU's unit cost now, or null |

`Memory\InMemoryBundles` keeps the bundles in an array, for tests and a single process.

## The cost through the logistics

An application using phil/commerce-logistics-bundle reads the unit cost from its `InventoryValuation` port, which
follows the method the shop chose:

```php
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\Money\Money;
use Phil\ProductBundles\Contract\ComponentCosts;

final readonly class ValuationCosts implements ComponentCosts
{
    public function __construct(private InventoryValuation $valuation)
    {
    }

    public function unitCost(string $sku): ?Money
    {
        return $this->valuation->unitCost($sku);
    }
}
```

## The stock through the logistics

The available units are what phil/commerce-logistics-bundle may still reserve of the SKU over its open locations:

```php
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\ProductBundles\Contract\ComponentStock;

final readonly class LogisticsStock implements ComponentStock
{
    public function __construct(private Stock $stock)
    {
    }

    public function available(string $sku): int
    {
        return $this->stock->available($sku);
    }
}
```

Selling issues each `StockDraw` of `SoldBundle::draws()` through the logistics; a return puts back each `StockDraw` of
`BundleRefund::restock`, which the logistics values at the cost the units left with.

## The prices through the catalogue

The unit price is the one the catalogue sells the component for on its own, in the channel's currency; return null
when it has none, and the bundle is not quoted.

## Storing

A store keeps the array `toArray()` gives — scalars and nulls only — and rebuilds the value with `fromArray()`, which
refuses a row out of shape. Index the bundle's SKU (unique), and the components' SKUs for `containing()`.
