# Stock

Stock is kept per SKU and per location — a warehouse, a store's back room —, and every movement is written to a
journal that is never changed. Goods ordered from suppliers are received through [Purchasing](purchasing.md), and the
convention items of an order placed online may go to print instead: see [Conventions](conventions.md).

```php
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;

$locations->addLocation(Location::of('warehouse', 'Entrepôt', priority: 10));
$locations->addLocation(Location::of('store-mtl', 'Magasin Montréal'));

$stock->receive('DICE-D20', 'warehouse', 120, $clock->now(), 'PO-2026-014');
$stock->transfer('DICE-D20', 'warehouse', 'store-mtl', 20, $clock->now(), 'TR-88');
$stock->count('DICE-D20', 'store-mtl', 18, $clock->now(), 'COUNT-11'); // the journal keeps −2
$stock->available('DICE-D20');                                        // 118
```

| Method of `Stock` | Effect |
|---|---|
| `receive(sku, location, quantity, at, reference)` | Adds units on hand |
| `count(sku, location, counted, at, reference)` | Sets what a count found; the journal keeps the difference |
| `transfer(sku, from, to, quantity, at, reference)` | Moves available units from one location to another |
| `available(sku)` | On hand less reserved, over the open locations |
| `reserve(order, at)` | Sets aside the tracked lines of a placed order, once |
| `release(orderNumber, at)` | Gives back what is still held for the order: it was cancelled |
| `ship(orderNumber, at)` | Takes what is still held for the order off the shelf: it left |
| `shipPart(orderNumber, sku, quantity, at)` | Takes part of what is held for one SKU off the shelf: a partial shipment, which `Shipments` makes |
| `allowBackorders(sku)`, `forbidBackorders(sku)`, `allowsBackorders(sku)` | Sells the SKU without stock, or no longer; whether it does |
| `backordered(sku)` | The units reserved beyond what is on hand, over every location |

- **A SKU is tracked once it has a stock level somewhere.** A product never received — a download, a service — is
  never reserved, and only lines of the type `product` are.
- **The configured lines of one product share its SKU**: two lines that differ by their options — two names printed
  on one shirt — are reserved together, from the same stock.
- **Reservations take from the open locations by priority**, highest first, then by code. A shortage on any line
  reserves nothing, and throws `StockRefused`.
- **Available is on hand less reserved**, never below zero; a count may find less on hand than is reserved, which
  the application then settles.
- **A unit leaves only from the shelf**: shipping more of a level than it holds on hand is refused before anything
  moves.
- **Only a placed order is reserved**: any other state throws `StockRefused`.
- **Reserving, releasing or shipping twice changes nothing.** An order that already has reservations is not reserved
  again — a listener run twice or a retry after a failed flush holds nothing more —, and only what is still held is
  released or shipped.
- **Reserving looks the stock up once per order**: one lookup of the open locations and one of the levels of all the
  order's product SKUs (`StockRepository::levelsOfSkus()`); lines of another type are never looked up.
- A closed location (`Location::close()`) is no longer reserved from; its stock stays counted.
- The reference — an order number, a delivery note, a count sheet — goes to the journal, which `StockRepository::journal()`
  reads back oldest first.

## Selling without stock

A SKU whose `StockPolicy` allows backorders is sold without stock — a flag, with neither a threshold nor a ceiling.

```php
$stock->allowBackorders('DICE-D20');
$stock->reserve($order, $clock->now());   // 5 ordered, 2 on hand: 2 reserved, 3 backordered
$stock->backordered('DICE-D20');          // 3
$stock->receive('DICE-D20', 'warehouse', 3, $clock->now(), 'PO-2026-015');
$stock->ship($order->number, $clock->now());
```

- What the open locations hold is reserved first, by priority; **the shortfall is reserved at the SKU's first open
  location**, beyond what it has available, and the journal records it as `backordered`.
- The backordered units leave once goods are received there: shipping them before is refused, with
  `StockRefused::notOnHand()`.
- A SKU only backorders where it is tracked — once it has a stock level at an open location; a SKU without a policy,
  or whose policy forbids backorders, is refused on a shortage as before.
- Without a `StockPolicyRepository`, `allowBackorders()` throws and no SKU backorders.

## Sales paid later

`DeferredStock` is the core's `StockCommitments`, tagged `phil_commerce.stock_commitments`: when the policy of a
deferred sale — a preorder, installments, a layaway — commits its stock, the core's `DeferredSales` calls it, and it
reserves the order through `Stock::reserve()`; when the sale is cancelled before it is paid in full, it releases the
reservations. Committing or releasing twice changes nothing. The moment comes from the PSR-20 clock.

## Storage

With DoctrineBundle:

| Table | Holds |
|---|---|
| `phil_commerce_logistics_location` | `code` (primary key), `name`, `priority`, `active` |
| `phil_commerce_logistics_stock_level` | `sku` and `location` (primary key), `on_hand`, `reserved`, `version` |
| `phil_commerce_logistics_stock_movement` | `id` (UUID v7), `sku`, `location`, `kind`, `on_hand_change`, `reserved_change`, `reference`, `moved_at`; indexed by SKU, location and moment, and by reference |
| `phil_commerce_logistics_reservation` | `id` (UUID v7), `order_number` (indexed), `sku`, `location`, `quantity`, `shipped`, `state` |
| `phil_commerce_logistics_stock_policy` | `sku` (primary key), `backorders` |

A stock level's version column refuses two movements made over the same copy.
