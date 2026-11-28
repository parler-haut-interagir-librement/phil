# Changelog

All notable changes to `phil/product-bundles` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `ProductBundle`: a SKU of its own over 1 to 50 `BundleComponent`, each SKU once with its units per bundle, never the
  bundle itself; `BundleKind::Assembled`, taxed as one product, or `BundleKind::Collection`, taxed component by
  component; stored by `toArray()` and read back by `fromArray()`, which refuses a row out of shape.
- `PricePolicy`: a fixed price above zero, or the components' prices less a phil/pricing `Rate` from 0 to 99.9999 %,
  rounded by a named `\RoundingMode`.
- `BundlePricing` and `BundleQuote`: the components' value, the price, the savings, and the price shared among the
  components by their values with `Money::allocate()`, by quantity when all are free; `PriceUnknown` when a component
  has no price in the bundle's currency.
- `BundleAvailability` and `BundleStock`: the bundles that can be sold, set by the scarcest component, never below zero.
- `ComposedCost`, `BundleCost` and `BundleMargin`: the cost of one bundle composed from its components' unit costs,
  unknown when one is unknown, and the margin with its rate over the price.
- `Bundles`: a bundle's quote, stock, cost and margin by SKU, and `sell()`, which refuses a sale beyond the stock with
  `SaleRefused` unless on back order.
- `SoldBundle`: the sale frozen on its order line — unit price, each component's share and unit cost, quantities —,
  its `StockDraw` per component, and `refund()` of whole bundles only, giving the amount, its shares, the units to put
  back and the line after; `ReturnRefused` beyond what is left. Stored and read back, refusing shares that do not add
  up to the unit price.
- `Cooperative\CooperativeBundle`: a bundle shared by 2 to 50 sellers with relative weights, approved by each, locked
  by the first approval, with a charity's share taken first; `shares()` of a revenue adding up to it to the unit, once
  every participant approved; stored and read back.
- The ports `Contract\BundleRepository`, `ComponentPrices`, `ComponentStock` and `ComponentCosts`, and
  `Memory\InMemoryBundles`.
- The fuzz target `product-bundles-stored` over the three stored rows, and the benchmark `BundleBench`.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
