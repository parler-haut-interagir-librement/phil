# Changelog

All notable changes to `phil/commerce-reports-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- The ports `SoldLines`, `ReturnedLines`, `StockFigures` and `StockedSkus`, and the value objects `SoldLine`,
  `ReturnedLine`, `StockFigure` and `StockedSku`, each bounded, with `InvalidFigure`.
- `SellThrough::ppm()`: units sold × 1 000 000 / (starting stock + units received), a half rounded up, null with
  nothing to sell from; `Dormancy`: dead stock after a number of days without a sale, 90 by default, counted from the
  clock.
- `SalesLedger` and `SalesTotals`: the lines sold and returned in a period, totalled by product, category, channel,
  location or cashier and by currency with `phil/money`, at most 50 000 totals.
- The reports `commerce_sales`, `commerce_sell_through` and `commerce_dead_stock`, tagged among the reports of
  `phil/reporting-bundle`, with the parameters `by` and `days`.
- `PosSoldLines`, the lines sold at the point of sale, read from the cash sessions closed in the period and the orders
  of the commerce core; `LogisticsStockFigures`, the stock figures read from the stock journal of the logistics module.
- The memory adapters of the four ports, and `PortsPass`, which aliases each port the application leaves alone.
- The option `dead_stock_days`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- The Flex recipe, in `recipe/0.1`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
