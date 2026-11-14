# Storage

The five ports of `phil/stockroom` are kept in six tables of one DBAL connection, named by the prefix (`phil_stockroom_`
by default). No ORM entity maps them: the bundle reads and writes them with DBAL, and its schema listener only adds them
to the schema the ORM generates.

## The tables

| Table | Port | One row per | Keys and indexes |
|---|---|---|---|
| `<prefix>container` | `Containers` | container: identifier, name, parent, code | primary key `id`; index `(parent, id)` for the children of a container |
| `<prefix>item` | `Items` | item: identifier, code, name, tracking, home, condition | primary key `id`; unique `code` |
| `<prefix>balance` | `Balances` | item and place holding pieces of it, with the quantity | primary key `(item, place)`; index `(place, item)` for what a place holds |
| `<prefix>movement` | `Journal` | movement: position, identifier, kind, item, quantity, from, to, moment, reference, note | primary key `position`, auto-incremented; unique `id`; indexes `(item, position)` and `(reference, position)` |
| `<prefix>load_list` | `LoadLists` | event that has a load list, even an empty one | primary key `event` |
| `<prefix>load_line` | `LoadLists` | line of a load list, in the list's order | primary key `(event, line)` |

A place — a container, an event or a holder — is kept as its key, `container/shelf-b`, which `Place::fromKey()` reads
back. A holder is the identifier the application gives, never a name or an e-mail address: the library refuses an `@`
in every identifier.

Identifiers, codes and place keys are columns of 140 characters. Names are at most 200 characters, notes at most 1,000,
as the library bounds them.

## Byte by byte

The identifiers compare byte by byte, as the library sorts them: the columns take the `ascii_bin` collation of MySQL and
MariaDB and the `C` collation of PostgreSQL; SQLite compares bytes already. `crate-A` and `crate-a` are two containers,
and a listing comes in the same order on every database and in memory: uppercase before lowercase, `Shelf-C` before
`shelf-a`.

## The journal, written once

The journal's adapter inserts, and has no statement that changes or deletes a movement. Each row takes the next value
of its auto-incremented position, which orders the journal as it was written: the movements of an item or of a
reference are read the latest first by an index that ends with the position, bounded by the limit asked. The moment of a
movement is kept as microseconds since the Unix epoch, in UTC, and read back in UTC to the microsecond.

## Reading back

Each row read is checked again by the library's value objects: a row that is not a container, an item, a movement or a
line the library keeps throws `InvalidStock`, and a column of the wrong type throws `UnexpectedValueException`, rather
than being guessed at. The adapters keep no state between calls: they are safe in a worker.

## Transactions and locks

`phil/stockroom` runs no transaction itself: a movement reads the balances of two places, then writes them and its
line of the journal in separate statements. `StockroomTransactions::run()` puts them in one transaction:

```php
$transactions->run(fn () => $stockroom->transfer('sword', 'shelf-a', 'crate-3', 12), 'sword');
$transactions->run(fn () => $deployments->deploy('larp-7'), ...array_keys($list->totals()));
```

The identifiers given after the work are locked first, `SELECT … FOR UPDATE` on the items' rows, in the order of their
identifiers so that two transactions never wait on each other's locks; at most 500 per transaction. A second movement of
the same item waits until the first is committed, then reads the balances it left. SQLite, which lets one writer at a
time, takes no row lock. What the work returns is returned once committed; what it throws rolls everything back and is
thrown again.

When a transaction is open on the same connection already, `run()` joins it: DBAL nests it in a savepoint, the locks are
held until the outer transaction ends, and the movement is kept with the outer writes or not at all.

A call that writes outside `run()` — saving a container or an item, planning a load list — is safe on its own: each is
one row, or one transaction of its own for a load list.

## Migrations

With the ORM, `StockroomSchemaListener` adds the tables to the schema of the store's connection only; another entity
manager on another connection never sees them. `doctrine:migrations:diff` writes the migration that creates them, and a
later version of the bundle that changes a table is followed by a new diff. Without the ORM, `phil:stockroom:schema`
shows or creates the missing tables.
