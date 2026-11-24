# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Phil\CommerceReportsBundle\PhilCommerceReportsBundle` | The bundle: its configuration `phil_commerce_reports`, its reports tagged `phil_reporting.report`, its adapters. |
| `Application\Contract\SoldLines` | The lines sold in a period. |
| `Application\Contract\ReturnedLines` | The lines returned in a period. |
| `Application\Contract\StockFigures` | The stock figures of a SKU over a period. |
| `Application\Contract\StockedSkus` | The SKUs in stock, with their names and categories. |
| `Application\Report\SalesReport` | The report `commerce_sales`: gross and net sales by a dimension and by currency. |
| `Application\Report\SellThroughReport` | The report `commerce_sell_through`: sell-through by SKU or by category. |
| `Application\Report\DeadStockReport` | The report `commerce_dead_stock`: the SKUs in stock without a sale for a number of days. |
| `Application\Sales\SalesLedger` | The lines sold and returned in a period, totalled by a dimension and by currency, at most 50 000 totals. |
| `Application\Sales\SalesTotals` | The totals of one value in one currency: units sold and returned, gross sales, discounts, returns, `net()`. |
| `Domain\Sales\SalesDimension` | `product`, `category`, `channel`, `location`, `cashier`: the value, the label and the heading of a line along each. |
| `Domain\Formula\SellThrough` | `ppm(sold, start, received)`: the sell-through in parts per million, a half rounded up, null with nothing to sell from. |
| `Domain\Formula\Dormancy` | `isDormant(figure, now, days)` and `daysWithoutSale(lastSoldAt, now)`; `DEFAULT_DAYS`, 90, and `MAX_DAYS`, 3650. |
| `Domain\ValueObject\SoldLine` | A line sold: SKU, product, category, channel, location, cashier, quantity, gross amount, discounts, moment. |
| `Domain\ValueObject\ReturnedLine` | A line returned: the same dimensions, quantity, amount given back, moment. |
| `Domain\ValueObject\StockFigure` | The starting stock, the units received, the units available and the last sale of a SKU. |
| `Domain\ValueObject\StockedSku` | A SKU in stock, its name and its category. |
| `Domain\Exception\InvalidFigure` | A line, a figure or a formula's input out of its bounds. |
| `Infrastructure\Pos\PosSoldLines` | `SoldLines` read from the point of sale and the commerce core's orders. |
| `Infrastructure\Logistics\LogisticsStockFigures` | `StockFigures` read from the logistics module's stock journal. |
| `Infrastructure\Memory\InMemorySoldLines`, `InMemoryReturnedLines`, `InMemoryStockFigures`, `InMemoryStockedSkus` | The ports on items given once. |
| `Infrastructure\DependencyInjection\PortsPass` | Aliases each port the application left alone to its module's adapter, or to its memory adapter. |

The namespaces above are relative to `Phil\CommerceReportsBundle`. `Domain\ValueObject\Text` and
`Domain\ValueObject\Units` are internal.
