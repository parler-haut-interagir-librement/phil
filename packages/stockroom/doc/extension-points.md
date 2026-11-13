# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## The stores

An application that keeps its stock implements the ports of `Phil\Stockroom\Contract` on its database:

| Port | Holds | Notes |
|---|---|---|
| `Containers` | The containers | `children()` in the order of their identifiers, bounded by its limit |
| `Items` | The items | `byCode()` finds the item a label names; codes are unique |
| `Balances` | The pieces of each item in each place | A quantity set to 0 removes the line; `of()` in the order of the places' keys, `at()` in the order of the items |
| `Journal` | The movements, written once | `ofItem()` and `ofReference()` the latest first, bounded by their limit |
| `LoadLists` | One load list per event | `save()` replaces the list of its event |

`Place::key()` gives a place as one string — `container/shelf-b` — that a store keeps in a column, and
`Place::fromKey()` reads it back. The stockroom runs no transaction itself: an application runs each call in one, so
that a movement and its balances are written together, and serializes the calls that move the same item — a row lock
on the item, for instance.

The adapters in memory, under `Phil\Stockroom\Memory`, show each contract; they implement `ResetInterface`.

## Reading the stock from another package

`Contract\StockReader` is what another package reads without moving anything: `item()`, `available()` — the pieces in
containers, none for an item that may not leave — and `holdings()`. `Stockroom` implements it; a package that only
reads depends on the port.

## The clock

Any PSR-20 clock: `Symfony\Component\Clock\NativeClock` in production, `MockClock` in tests. Moments are kept in UTC
whatever its zone.
