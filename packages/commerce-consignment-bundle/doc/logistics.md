# With the logistics module

The bundle splits by owner the stock `phil/commerce-logistics-bundle` keeps, and moves it only through the public
services of that module, behind the `StockMovements` and `ShippedUnits` ports. It needs the version of the logistics
module that announces shipments, takes customer returns and adjustments, and gives purchase order lines a unit cost and
an owner.

## How the stock moves

`LogisticsStockMovements` is the adapter of `StockMovements`:

| The ledger | The logistics module |
|---|---|
| A receipt | `Stock::receive()` per SKU, under the receipt's reference; with a purchase order, `Purchasing::receive()` |
| A transfer | `Stock::transfer()`, under the transfer's reference |
| A count's difference | `Stock::adjust()` by the difference, never below zero |
| A return of an order the logistics module shipped | `Stock::returnFromCustomer()` under the order's number |
| A return of a sale it never shipped, a counter sale | `Stock::receive()` under the sale's reference |
| Units given back to their consignor | `Stock::adjust()` by the units taken off, under the slip's reference |
| A buy-out | Nothing moves: only the owner changes |
| An adoption | Nothing moves; the units on hand are read from the level |

`ConsignmentStock` writes the ledger first, then moves the stock: a refusal of the ledger moves nothing. Neither
commits: flush once, so that the ledger and the stock are written in one transaction. After a refusal of either, do
not flush: the unit of work holds what the ledger had started to write.

The logistics journal tells the kinds apart: a count's difference and units given back are `adjusted`, a customer's
return is `returned`. A count the ledger finds short by more units than the level has on hand — units the logistics
module shipped before their sale was written — takes off what is on hand and no more.

## Purchase orders

A receipt against a purchase order is received at the order's location, and each of its lines must agree with what
the order gives for its SKU:

- an owner on the order line: the receipt line is consigned by that consignor;
- a unit cost on the order line: a line the shop buys has that unit cost; a consigned line has none to compare.

What the order line leaves out is not checked. A line that disagrees is refused with `ConsignmentRefused` before any
unit moves.

```php
$purchasing->order('PO-12', 'distributor', 'store', [
    'BOOK-1' => PurchaseLine::of(4, Money::of(1200, Currency::of('CAD'))),
    'ZINE-7' => PurchaseLine::of(3, owner: 'author-ann'),
], $clock->now());

$consignmentStock->receive(Receipt::of('DN-2', 'store', [
    ReceiptLine::purchased('BOOK-1', 4, Money::of(1200, Currency::of('CAD'))),
    ReceiptLine::consigned('ZINE-7', 3, 'author-ann', ConsignmentTerms::share(Money::of(300, Currency::of('CAD')))),
], 'PO-12'), $clock->now());
```

## Shipped orders

The logistics module reserves the units of a placed order and ships them; a shipment is when units leave. Each call to
`Stock::ship()` or `Stock::shipPart()` that moves units dispatches `UnitsShipped`, with the order's number, before
anything is committed. `UnitsShippedListener` hears it and calls `ShippedOrders::record()` with the order, at the
moment the units left: the sales join the shipment's transaction, written by the same flush.

```php
$logistics->ship($order->number, $clock->now());
$entityManager->flush(); // the shipment and its sales
```

With `record_shipments: false`, the application writes the sales itself, after the shipment or in a worker:

```php
$shippedOrders->record($order, $clock->now());

// or, in a worker on a bus with Doctrine's transaction middleware:
$bus->dispatch(new RecordShipment($order->number));
```

`LogisticsShippedUnits` reads the order's reservations: each holds a SKU at a location, and counts the units that left
with it, those that came back since included. `ShippedOrders` sells, in the ledger and under the order's number, the
units shipped from each location and not written yet, at the unit price of the order's product lines in their order —
two configured lines of one product share its SKU and may differ in price. A second call writes only what shipped
since: after a partial shipment, two shipments before one flush, a message after the event, or a retry of a failed
flush, nothing is written twice. More units shipped than the order holds of a SKU are refused.

The price is the line's unit price, before the order's adjustments: a discount on the whole order does not lower the
margin of each unit.
