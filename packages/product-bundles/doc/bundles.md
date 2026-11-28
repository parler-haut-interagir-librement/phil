# Bundles

## What a bundle is

A `ProductBundle` is a SKU of its own over the products it holds:

| Part | Rule |
|---|---|
| `sku` | Up to 64 letters, digits, `_`, `.`, `/` or `-`, starting with a letter or a digit. |
| `kind` | `BundleKind::Assembled` or `BundleKind::Collection`: how it is taxed. |
| `currency` | The currency its price is in; a fixed price must be in it. |
| `components` | From 1 to 50 `BundleComponent`, each SKU once, never the bundle's own SKU. |
| `price` | A `PricePolicy`: a fixed price or a discount on the components. |

A `BundleComponent` is a SKU and the units of it one bundle holds, from 1 to 1,000. Two units of the same product are
one component of quantity 2, not two components: `ProductBundle` refuses a SKU given twice.

A bundle is immutable. To change its components or its price, build a new one and save it over the old; the lines
already sold keep what they were sold with.

## The two kinds

| Kind | Sold as | Taxed | Returned |
|---|---|---|---|
| `Assembled` | One product, on one line | On its whole price, by the bundle's own tax category | Whole |
| `Collection` | One line | Component by component, each at its own rate on its share of the price | Whole |

`BundleKind::taxedAsOne()` tells the two apart. A collection suits a print book sold with its PDF, where the print book
and the file may be taxed differently, or every title of a creator. A bundle of either kind is returned whole: a
component is never taken back alone. A rebate between two products that keep a line each is a promotion, which
refunds one product at its price less the rebate.

## Storing a bundle

`toArray()` gives scalars only, for a JSON column or columns of their own; `fromArray()` reads it back and refuses a
row out of shape with `InvalidBundle`:

```php
[
    'sku' => 'STARTER',
    'kind' => 'assembled',
    'currency' => 'CAD',
    'components' => [['sku' => 'RULES', 'quantity' => 1], ['sku' => 'DICE', 'quantity' => 2]],
    'price' => ['fixed' => null, 'discount_ppm' => 150000, 'rounding' => 'HalfAwayFromZero'],
]
```

`Contract\BundleRepository::containing()` gives the SKUs of the bundles that hold a component, in order and bounded,
so that a change of a component's price, stock or cost can refresh the bundles that show it.
