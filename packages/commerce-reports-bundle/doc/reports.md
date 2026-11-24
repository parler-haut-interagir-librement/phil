# The reports

Each report is a `ReportDefinition` of the reporting bundle: a request gives its period, its start included and its
end excluded, and its parameters.

```php
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;

$queue->queue(new ReportJob('commerce_sales', 'csv', new ReportRequest($period, ['by' => 'category']), 'mail', ['manager@example.com']));
```

## Gross and net sales: `commerce_sales`

One row per value of the dimension and per currency, sorted by value, then by currency.

| Parameter | Values | Default |
|---|---|---|
| `by` | `product`, `category`, `channel`, `location`, `cashier` | `product` |

| Column | What it holds |
|---|---|
| SKU, or the dimension | The value; empty for the lines without one, a sale without a cashier for instance. |
| Product, or Name | The product's name, or the value again. |
| Currency | The ISO 4217 code of the amounts of the row. |
| Units sold | The units of the lines sold in the period. |
| Units returned | The units of the lines returned in the period. |
| Gross sales | The lines' prices times their quantities, before discounts, without taxes or tips. |
| Discounts | The discounts of the lines sold, as a positive amount. |
| Returns | The amounts given back for the lines returned, without taxes. |
| Net sales | `gross − discounts − returns`. |

The amounts are decimals to the precision of their currency, computed with `phil/money` in minor units: never a
float, never rounded, and never added across currencies — a product sold in dollars and in euros has two rows.

A return counts in the period it was received, whatever the period of its sale: a period that gives back more than
it sells has negative net sales, and a product only returned in the period has a row with no unit sold.

## Sell-through: `commerce_sell_through`

| Parameter | Values | Default |
|---|---|---|
| `by` | `sku`, `category` | `sku` |

The rows are the SKUs that `StockedSkus` lists and those sold in the period, sorted by SKU; by category, their sums,
sorted by category, the SKUs without one under an empty category.

```text
sell-through (ppm) = units sold × 1 000 000 / (starting stock + units received)
```

- the result is rounded to the nearest whole part per million, a half rounded up: 1 sold of 3 gives 333 333, 2 of 3
  give 666 667, 1 of 2 000 000 gives 1;
- with nothing to sell from — a starting stock and units received that add up to zero or less — the sell-through is
  empty, never a division by zero;
- a period that sold more than it had, from backorders, gives more than 1 000 000;
- the percentage is the parts per million divided by 10 000: 250 000 ppm is 25 %.

| Column | What it holds |
|---|---|
| SKU, or Category | The SKU, or the category. |
| Product, or SKUs | The product's name, or the number of SKUs of the category. |
| Starting stock | The units on hand when the period starts. |
| Units received | The units received during the period. |
| Units sold | The units of the lines sold in the period, returns not deducted. |
| Sell-through (ppm) | The formula above; empty with nothing to sell from. |
| Sell-through (%) | The same, as a percentage. |

## Dead stock: `commerce_dead_stock`

Every SKU that `StockedSkus` lists, in its order, that has units available and has not sold for `days` days, or never
sold.

| Parameter | Values | Default |
|---|---|---|
| `days` | a whole number from 1 to 3650, or the same written as text | `dead_stock_days`, 90 |

- the age is counted from now, as `Psr\Clock\ClockInterface` gives it, not from the end of the period;
- a last sale exactly `days` days ago is dead stock; one a second later is not: the moments are compared by `<` and
  `>`, never for equality;
- a SKU with no unit available is never dead stock, whatever its last sale.

| Column | What it holds |
|---|---|
| SKU | The SKU. |
| Product | Its name. |
| Category | Its category, empty without one. |
| Units available | The units available now. |
| Last sale | The day of its last sale, empty when it never sold. |
| Days without a sale | The whole days from the last sale to now, empty when it never sold. |

## Bounds

A period gives at most 50 000 totals of sales and reads at most 100 000 SKUs for its sell-through; beyond, the report
stops with the reporting bundle's `ReportTooLarge`. A parameter out of its values stops it with `InvalidReportJob`.
The reports hold their totals in memory, never the lines, which the ports yield one at a time.
