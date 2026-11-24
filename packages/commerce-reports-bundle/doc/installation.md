# Installation

```sh
composer require phil/commerce-reports-bundle
```

Then register the bundle in `config/bundles.php`, after the reporting bundle:

```php
Phil\ReportingBundle\PhilReportingBundle::class => ['all' => true],
Phil\CommerceReportsBundle\PhilCommerceReportsBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later, Symfony 8.2 or later, `phil/reporting-bundle`, `phil/money` and
`phil/commerce-bundle`. The reports appear among the reporting bundle's reports, under the keys `commerce_sales`,
`commerce_sell_through` and `commerce_dead_stock`: its periods, formats, exports, schedules and deliveries apply to
them as to any other report.

## Connecting the ports

The reports read four ports. The bundle aliases each one that the application leaves alone:

| Port | With | Without |
|---|---|---|
| `SoldLines` | `phil/commerce-pos-bundle` and the commerce core's orders: `PosSoldLines` | an empty memory adapter |
| `StockFigures` | `phil/commerce-logistics-bundle`: `LogisticsStockFigures` | an empty memory adapter |
| `ReturnedLines` | — | an empty memory adapter |
| `StockedSkus` | — | an empty memory adapter |

`ReturnedLines` and `StockedSkus` have no module that lists them yet: alias them to services of the application, or
the sales count no return and the stock reports know only the SKUs sold in their period.

```yaml
# config/services.yaml
services:
    Phil\CommerceReportsBundle\Application\Contract\ReturnedLines: '@App\Reports\ShopReturns'
    Phil\CommerceReportsBundle\Application\Contract\StockedSkus: '@App\Reports\ShopSkus'
```

An alias the application sets is kept; [the extension points](extension-points.md) describe each port.

`DeadStockReport` reads the time from `Psr\Clock\ClockInterface`, which FrameworkBundle registers with
`symfony/clock`.
