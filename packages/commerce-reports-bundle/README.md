# PHIL Commerce Reports Bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/commerce-reports-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/commerce-reports-bundle)

**Know what sells and what sits on the shelf: gross and net sales by product, category, channel, location and
cashier, sell-through and dead stock reports.**

phil/commerce-reports-bundle is a Symfony bundle. It adds three reports to
[phil/reporting-bundle](https://github.com/parler-haut-interagir-librement/reporting-bundle) — exported as CSV, TSV
or JSON, scheduled and delivered by that bundle — and reads the commerce modules through ports of its own: the lines
sold, the lines returned, the stock figures of a SKU and the SKUs in stock.

## Status

| Part | State |
|---|---|
| Gross and net sales by product, category, channel, location and cashier, one row per currency (`commerce_sales`) | Implemented |
| Sell-through in parts per million, by SKU and by category (`commerce_sell_through`) | Implemented |
| Dead stock after a number of days without a sale (`commerce_dead_stock`) | Implemented |
| The ports `SoldLines`, `ReturnedLines`, `StockFigures`, `StockedSkus`, with memory adapters | Implemented |
| The lines sold at the point of sale (`PosSoldLines`) and the stock figures of the logistics module (`LogisticsStockFigures`) | Implemented |
| Margin, cost of goods sold, turnover, days of stock, gross margin return on inventory and shrinkage | Planned: they need the cost of the stock, which the logistics module does not record yet |
| Sales and stock split by owner of the stock, for consignment | Planned |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and connecting the ports. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [The reports](doc/reports.md) | Each report, its parameters, its columns and its formulas. |
| [Extension points](doc/extension-points.md) | The ports, the adapters that ship with the bundle, and what each module does not list yet. |
| [Testing](doc/testing.md) | The bundle's tests. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\ReportingBundle\PhilReportingBundle::class => ['all' => true],
Phil\CommerceReportsBundle\PhilCommerceReportsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_commerce_reports.yaml
phil_commerce_reports:
    dead_stock_days: 90
```

```sh
bin/console phil:reporting:export commerce_sales --period=last_month --output=var/sales.csv
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
