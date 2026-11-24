# The ownership ledger

The logistics module knows how many units of a SKU are on hand at each location. The ledger knows whose they are.

## Layers

A **layer** holds units of one SKU, at one location, owned by one owner, received together on the same terms:

| Owner | What the layer keeps |
|---|---|
| The shop | The unit cost it paid. |
| A consignor | The terms agreed: a share of each unit sold, in `Money`, or the shop's commission, in parts per million. |

A consignor is the application's identifier of a supplier, an author or an artisan — the supplier code of a purchase
order, the identifier of a contact —, 1 to 64 characters compared byte by byte. `Owner::shop()` and
`Owner::consignor('author-17')` name owners; `ConsignmentStock::split()` lists what each one holds of a SKU, at one
location or at all of them, the shop first, then the consignors in the order of their identifiers:

```php
foreach ($stock->split('BOOK-123', 'store') as $part) {
    $part->owner->consignor;   // null for the shop
    $part->quantity;
}
```

Each receipt line opens a layer. A transfer, or a return to another location, puts units in a layer of their own at
the other location, which keeps the receipt's instant, cost or terms and names the receipt's layer as its **origin**;
units that come back to a location where a layer of the same receipt already is go back to that layer. A layer is
never deleted: an emptied one stays in the table with nothing remaining, and the lookups of units on hand leave it out.

## Entries

Every movement of a layer is an **entry** of the ledger, never changed nor deleted: the layer, the SKU, the location,
the owner, the kind, the units gained or lost, the reference, the instant, and for a sale, a return or a buy-out the
unit price, what the units brought in and what they cost or owe their consignor.

| Kind | Written by | Units |
|---|---|---|
| `received` | `receive()` | gained |
| `adopted` | `adopt()` — stock on hand whose owner the ledger starts to keep | gained |
| `sold` | `sell()` | lost |
| `returned` | `returnSold()` | gained |
| `transferred_out`, `transferred_in` | `transfer()` | lost at the source, gained at the target |
| `counted` | `count()` | the difference a count found |
| `given_back` | `UnsoldStock::giveBack()` — unsold units back to their consignor | lost |
| `bought_out`, `bought_in` | `UnsoldStock::buyOut()` — the shop buys a consignor's units at the agreed cost | lost by the consignor's layer, gained by the shop's |

The statements of the consignors are read from these entries: see [Statements](statements.md). Unsold units given back
and bought out are explained in [Unsold units](unsold-units.md).

## The issue order

When the shop holds units of a SKU it bought and units on consignment, the issue order says which leave first:

| Order | Units that leave first |
|---|---|
| `owned_first` | The shop's own: its money comes back before it sells for others. |
| `consigned_first` | The consigned ones: what the shop does not own leaves before what it paid for. |
| `oldest_first` | The oldest receipt, whoever owns it. |

Within each group, and between consignors, the oldest receipt leaves first; between layers received at the same
instant, the one written first — the earlier line of the receipt. The order applies to sales and to transfers; it comes
from the configuration, for every SKU or per SKU (see [Configuration](configuration.md)).

## Sales

`sell()` takes units of the SKU at a location in the issue order, writes one `sold` entry per layer it took from, and
returns a `Sale`: its lines, one per layer, each with its owner, its units and its `Margin`; `quantityOf()` an owner;
`margin()` over every line; `owedToConsignors()`, by consignor. A unit price is zero or more, and the same currency as
the layers' costs and shares. A sale of more units than the location holds refuses everything and moves nothing.

`sell()` writes the sale to the ledger only: the stock leaves with the order's shipment, in the logistics module.
`ShippedOrders` writes the sales of shipped orders after each shipment: see
[With the logistics module](logistics.md#shipped-orders).

## Returns

`returnSold()` takes back units of a sale, under the sale's reference, to the layers they left: the units sold last
come back first — within one sale, the layer taken last. Each returned unit goes back to its owner with the price it
was sold at, and its margin is the sale's, negated: the commission comes off, and the consignor's share is no longer
owed. Units brought back to another location than the one they were sold at go to a layer at that location, with
their cost or terms. A sale gives back at most what it sold less what came back already; a return beyond that is
refused.

## Transfers

`transfer()` moves units of a SKU between two locations in the issue order, or of one owner only. Each unit keeps its
owner, its cost or terms and its receipt's instant, so the oldest-first order stays true at the target.

## Counts

`count()` sets what a count found of a SKU at a location and returns the difference:

- units missing leave the shop's own layers first, then the consigned ones, oldest first;
- units found go to the shop's newest layer at the location, or to a layer at the location split from the shop's last
  receipt of the SKU.

A count of one owner — `count(..., owner: Owner::consignor('author-17'))` — compares and changes that owner's units
only, and units found go to that owner's newest layer. Units found of an owner who never received the SKU are refused:
nothing tells their cost or their terms; receive them instead.

## Concurrency

Nothing is committed by the ledger: the caller flushes, in one transaction, the ledger and the stock it moved. Each
layer carries a version: two requests that take from the same layer at once cannot both write, and the second flush
throws Doctrine's `OptimisticLockException`. Retry the operation from the start, with a fresh entity manager. The
services keep no state between requests.
