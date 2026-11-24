# With the logistics module

The bundle splits by owner the stock `phil/commerce-logistics-bundle` keeps, and moves it only through the public
services of that module, behind the `StockMovements` and `ShippedUnits` ports.

## How the stock moves

`LogisticsStockMovements` is the adapter of `StockMovements`:

| The ledger | The logistics module |
|---|---|
| A receipt | `Stock::receive()` per SKU, under the receipt's reference; with a purchase order, `Purchasing::receive()` |
| A transfer | `Stock::transfer()`, under the transfer's reference |
| A count's difference | `Stock::count()` with what the level had on hand plus the difference |
| A return | `Stock::receive()` under the sale's reference |
| Units given back to their consignor | `Stock::count()` with what the level had on hand less the units, under the slip's reference |
| A buy-out | Nothing moves: only the owner changes |
| An adoption | Nothing moves; the units on hand are read from the level |

`ConsignmentStock` writes the ledger first, then moves the stock: a refusal of the ledger moves nothing. Neither
commits: flush once, so that the ledger and the stock are written in one transaction.

## Shipped orders

The logistics module reserves the units of a placed order and ships them; a shipment is when units leave. After
`Stock::ship()` or `Stock::shipPart()`, call `ShippedOrders::record()` with the order, or dispatch `RecordShipment`
with its number to a worker:

```php
$logistics->ship($order->number, $clock->now());
$shippedOrders->record($order, $clock->now());
$entityManager->flush();

// or, in a worker on a bus with Doctrine's transaction middleware:
$bus->dispatch(new RecordShipment($order->number));
```

`LogisticsShippedUnits` reads the order's reservations: each holds a SKU at a location, and what it shipped is its
quantity less what it still holds or gave back. `ShippedOrders` sells, in the ledger and under the order's number, the
units shipped from each location and not written yet, at the unit price of the order's product lines in their order —
two configured lines of one product share its SKU and may differ in price. A second call writes only what shipped
since: after a partial shipment, after a retry of a failed flush, nothing is written twice. More units shipped than
the order holds of a SKU are refused.

The price is the line's unit price, before the order's adjustments: a discount on the whole order does not lower the
margin of each unit.

## What the logistics module does not expose

The adapters use what the logistics module publishes and nothing else. What it does not offer yet:

| Missing | Effect here |
|---|---|
| An event when units ship — order, SKU, location, units | The application calls `ShippedOrders` after each shipment, or dispatches `RecordShipment`; nothing writes a sale by itself. |
| A movement kind for customer returns, and a service that puts sold units back on the shelf | A return is received again under the sale's reference: the logistics journal writes it as a receipt. |
| A count that changes a level by a difference | The adapter reads the level, then sets what it had plus the difference: two counts of one level at once rely on the level's version in the logistics module. |
| A movement that takes units off the shelf by a quantity, for units given back to their consignor | The adapter reads the level, refuses more units than it has on hand, then sets it to what it had less the units through `Stock::count()`: the logistics journal writes a count under the slip's reference. |
| The unit cost and the owner on a purchase order's lines | The receipt carries them in this bundle; the purchase order counts units only. |
