# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Read the sales of another channel | Alias `SoldLines` to your own service |
| Count the returns | Alias `ReturnedLines` to your own service |
| Read the stock elsewhere | Alias `StockFigures` to your own service |
| List the SKUs in stock | Alias `StockedSkus` to your own service |

An alias or a service the application defines under a port's name is kept; `PortsPass` fills only the ports left
alone. A port yields what it reads one item at a time: the reports never hold the lines.

## The ports

All four live in `Phil\CommerceReportsBundle\Application\Port`.

| Port | Method | What it gives |
|---|---|---|
| `SoldLines` | `between(Period): iterable<SoldLine>` | The lines sold in the period: SKU, product, category, channel, location, cashier, quantity, gross amount and discounts in one currency, moment of the sale. |
| `ReturnedLines` | `between(Period): iterable<ReturnedLine>` | The lines returned in the period: the same dimensions, quantity, the amount given back without taxes, moment of the return. |
| `StockFigures` | `of(string $sku, Period): StockFigure` | The units on hand at the period's start, received during it, available now, and the moment of the last sale; zeros and no sale for an unknown SKU. |
| `StockedSkus` | `all(): iterable<StockedSku>`, `find(string $sku): ?StockedSku` | The SKUs in stock, each once, with their names and categories. |

A dimension a module does not know — a sale without a cashier, a product without a category — is `null`. The value
objects refuse an empty text, a text longer than 200 characters, a quantity outside 1 to a billion and a negative
amount, with `InvalidFigure`.

## The adapters

| Adapter | Port | Reads |
|---|---|---|
| `PosSoldLines` | `SoldLines` | The cash sessions closed in the period (`CashSessionRepository::closedBetween()`), their sales (`RegisterSaleRepository::salesOf()`), each sale's register (`RegisterRepository::findRegister()`) and order (`OrderRepository::find()`). |
| `LogisticsStockFigures` | `StockFigures` | The levels of the SKU (`StockRepository::levelsOf()`) and the journal of each (`StockRepository::journal()`). |
| `InMemorySoldLines`, `InMemoryReturnedLines`, `InMemoryStockFigures`, `InMemoryStockedSkus` | each its port | The items given to their constructor: for tests, and the default of a port without a module. |

`PosSoldLines` counts a sale once its cash session is closed, in the period it was rung in; the gross amount of a
line is its subtotal with its options, less the taxes its prices include; its discounts are its promotions and its
share of the order's own promotions, split by gross amount with `Money::allocate()`. Tips, delivery charges, fees and
cancelled orders are left out. The category of a line is the one `StockedSkus` gives its SKU.

`LogisticsStockFigures` sums the on-hand changes before the period for the starting stock, the `received` moves inside
it for the units received — transfers between locations move no unit of the SKU as a whole —, the available units of
the levels now, and takes the latest `shipped` move that took units off the shelf as the last sale. It reads each
journal whole: its cost grows with the moves of the SKU.

## What the modules do not list yet

These reads have no public API in the commerce modules; the bundle keeps the port and its memory adapter until they
do:

- the commerce core lists no orders placed in a period: `OrderRepository` finds one order by its number, so the sales
  of the online channels reach `SoldLines` only through the application;
- the point of sale lists no sales rung in a period: `PosSoldLines` reaches them through the sessions closed in it, and
  misses a sale of a session still open or closed after the period;
- the returns module lists no returns received in a period: `ReturnRepository` lists the returns of an order, and
  those authorized before a moment;
- neither the catalogue nor the logistics module lists the SKUs it keeps: `ProductRepository` and `StockRepository`
  find a SKU, or the levels of SKUs already named.
