# Installation

```sh
composer require phil/product-bundles
```

The package needs PHP 8.5 or later. It requires phil/money, for amounts in their currency, and phil/pricing, for the
discount, the margin and their rates in parts per million.

## What the application provides

| What | Why |
|---|---|
| A store for the bundles, implementing `Contract\BundleRepository` | Keeps what `ProductBundle::toArray()` gives, by SKU, and finds the bundles that hold a component. |
| The components' prices, implementing `Contract\ComponentPrices` | The unit price of a SKU in a currency, as the catalogue sells it on its own. |
| The components' stock, implementing `Contract\ComponentStock` | The units of a SKU that can be sold now. |
| The components' costs, implementing `Contract\ComponentCosts` | The unit cost of a SKU now, as the stock valuation says. |
| A column on the order line | Keeps what `SoldBundle::toArray()` gives, for the return and the reports. |
| A store for cooperative bundles, when it sells them | Keeps what `CooperativeBundle::toArray()` gives. |

`Memory\InMemoryBundles` serves tests and a single process. The [extension points](extension-points.md) show adapters
over phil/commerce-catalog-bundle and phil/commerce-logistics-bundle.

## Wiring

The services take their ports in their constructors and keep no state between calls:

```php
$bundles = new Bundles(
    $bundleRepository,
    new BundlePricing($componentPrices),
    new BundleAvailability($componentStock),
    new ComposedCost($componentCosts),
);
```

In a Symfony application, declare them as services with autowiring; they need no reset between requests.
