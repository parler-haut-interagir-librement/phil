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
$stock->adjust('DICE-D20', 'warehouse', -1, $clock->now(), 'DAMAGED'); // one die written off
$stock->available('DICE-D20');                                        // 117
```

| Method of `Stock` | Effect |
|---|---|
| `receive(sku, location, quantity, at, reference, unitCost, owner)` | Adds units on hand; with costing, at their unit cost, or as an owner's units — see [Costing](costing.md) |
| `count(sku, location, counted, at, reference, reason)` | Sets what a count found; the journal keeps the difference, and its reason when given |
| `adjust(sku, location, difference, at, reference)` | Changes what is on hand by a difference, never below zero; the journal keeps it as `adjusted` |
| `adjustFor(sku, location, difference, reason, at, reference)` | Adjusts as `adjust()` does, for a `VarianceReason` the journal keeps beside the difference |
| `transfer(sku, from, to, quantity, at, reference)` | Moves available units from one location to another |
| `available(sku)` | On hand less reserved, over the open locations |
| `reserve(order, at)` | Sets aside the tracked lines of a placed order, once |
| `release(orderNumber, at)` | Gives back what is still held for the order: it was cancelled |
| `ship(orderNumber, at)` | Takes what is still held for the order off the shelf: it left |
| `shipPart(orderNumber, sku, quantity, at)` | Takes part of what is held for one SKU off the shelf: a partial shipment, which `Shipments` makes |
| `returnFromCustomer(orderNumber, sku, location, quantity, at)` | Puts units a customer sent back on a shelf, under the order's number; the journal keeps them as `returned` |
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
- **Each movement has its kind** in the journal: `received`, `counted`, `adjusted`, `transferred_out`, `transferred_in`,
  `reserved`, `backordered`, `released`, `shipped` and `returned`.

## Adjusting by a difference

`adjust()` changes what a level holds on hand by a difference — the units a count found missing or extra, a damaged
unit written off — where `count()` sets an absolute figure.

```php
$stock->adjust('DICE-D20', 'warehouse', -2, $clock->now(), 'COUNT-12');   // two missing
$stock->adjust('DICE-D20', 'warehouse', 1, $clock->now(), 'COUNT-12');    // one found behind the shelf
```

- **The difference applies to the level as it is read now.** `count()` overwrites what was on hand with the figure it
  is given, losing a movement made since the count was taken; `adjust()` does not.
- **Never below zero**: a difference that would leave less than nothing on hand throws `StockRefused::belowZero()`,
  and a difference of zero throws `StockRefused::quantity()`. Like a count, it may leave less on hand than is reserved,
  which the application then settles.
- **Two adjustments of one level at once both count.** Each request reads the level, adds its difference and flushes;
  the level's version column refuses the second flush with Doctrine's `OptimisticLockException`. The caller retries
  that unit of work in a new entity manager: the difference applies again, to the level as the first one left it.
- The reference goes to the journal: a count sheet, a damage report. `adjustFor()` adds a typed reason — damaged,
  theft, lost, expired, a count error, a supplier error, internal use, donated —, kept in the movement's `reason`; with
  costing, the cost journal keeps the value of the units at cost beside it. See [Costing](costing.md#variances).

## Customer returns

`returnFromCustomer()` puts units that a customer sent back on a shelf, under the order's number.

```php
$stock->ship($order->number, $clock->now());                                    // 3 dice left
$stock->returnFromCustomer($order->number, 'DICE-D20', 'returns', 2, $clock->now()); // 2 came back
```

- **Never more than left and did not come back yet**: the order's reservations of the SKU tell how many of its units
  shipped, and how many already came back; a return beyond them throws `StockRefused::overReturned()` and moves
  nothing. A SKU the order never shipped through its reservations — one not tracked — takes nothing back.
- **Any location**: the units go back where the caller says — the warehouse, a returns area —, which need not be
  where they left from.
- **The journal tells a return from a receipt**: the movement is `returned`, under the order's number.
- `Reservation::shipped()`, `returned()` and `returnable()` give, per reservation, what left, what came back and what
  may still come back. Whether a returned unit can be sold again is the application's to decide; it moves it with
  `transfer()` or `adjust()`.
- **With costing, the units come back at the cost they left at**, never at the day's cost — see
  [Costing](costing.md#customer-returns).
- With `phil/commerce-returns-bundle`, its logistics restocking, or a `Restocking` of the application's own, calls
  `returnFromCustomer()` when a return is received.

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
| `phil_commerce_logistics_stock_movement` | `id` (UUID v7), `sku`, `location`, `kind`, `on_hand_change`, `reserved_change`, `reference`, `moved_at`, `reason` (null without one); indexed by SKU, location and moment, and by reference |
| `phil_commerce_logistics_reservation` | `id` (UUID v7), `order_number` (indexed), `sku`, `location`, `quantity`, `shipped`, `returned` (0 by default), `state` |
| `phil_commerce_logistics_stock_policy` | `sku` (primary key), `backorders` |

A stock level's version column refuses two movements made over the same copy.

A database created for 0.1.0-alpha gains one column per table that changed and the two tables of costing, and nothing
else changes: `returned` in `phil_commerce_logistics_reservation`, an integer that is 0 for the existing rows,
`line_terms` in `phil_commerce_logistics_purchase_order` — see [Purchasing](purchasing.md) —, `reason` in
`phil_commerce_logistics_stock_movement`, null for the existing rows, and `phil_commerce_logistics_cost_layer` and
`phil_commerce_logistics_cost_entry` — see [Costing](costing.md#storage). Generate the migration with Doctrine's
`doctrine:migrations:diff`, or `doctrine:schema:update --dump-sql` to read it first.
