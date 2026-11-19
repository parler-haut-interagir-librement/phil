# Reservations

`Reservations` is the service an application calls. Each method takes the resource or reservation by its identifier
and moments in any time zone, read on the resource's clock.

| Method | What it does |
|---|---|
| `check(resource, start, end, quantity, replacing)` | The `Decision` a request would get now, booking nothing |
| `hold(resource, start, end, quantity, holder)` | A reservation held for the resource's hold time |
| `book(resource, start, end, quantity, holder)` | A reservation confirmed at once |
| `confirm(id)` | Confirms a hold; an expired hold is checked again first |
| `reschedule(id, start, end, quantity)` | Moves or resizes a reservation held or confirmed, checked without itself |
| `cancel(id)` | Frees its units |
| `slots(resource, from, until, minutes, quantity, step, limit)` | The free slots a request asked now would get |
| `expireHolds(limit)` | Marks the holds whose moment came as expired |

A refused request throws `ReservationRefused`, whose `decision` holds every reason and the reservations it meets. An
unknown or inactive resource, or an unknown reservation, throws `NotFound`; a change the reservation's state does not
allow — confirming a cancelled reservation, moving an expired one — throws `InvalidReservation`. Every exception
implements `Phil\Reservation\Exception\ReservationException`.

## Holds

A hold keeps its units until `holdExpiresAt()`: an application holds while the holder pays or fills a form, then
confirms. Past its moment, a hold no longer counts against the capacity, marked expired or not. `confirm()` on an
expired hold checks it again, its notice and booking window counted from when it was made, and confirms it when its
units are still free.

## The lock

`hold()`, `book()`, `confirm()` and `reschedule()` run under the resource's lock: the Doctrine repository opens a
transaction and first updates the resource's row, so that the database holds every other request on that resource
until the reservation is saved. Two requests for the last unit are decided one after the other, on MySQL, PostgreSQL
and SQLite alike. A refusal rolls the transaction back and leaves the entity manager open.

The integration suite proves it on MySQL 8.4 and PostgreSQL 17: six processes, each on its own connection, ask for
the last unit of a resource at the same instant; one books it and five are refused. The lock reads nothing stale on
either: on MySQL under `REPEATABLE READ`, the reservations are read after the row lock is granted, so the snapshot of
the transaction already holds the reservation committed before it; on PostgreSQL under `READ COMMITTED`, each
statement sees what was committed when it starts.

`cancel()` and `expireHolds()` free units and take no lock. A reservation carries a version: a change saved on a copy
read before another change was saved — a hold cancelled while the expiry run marks it expired — fails with Doctrine's
`OptimisticLockException` instead of overwriting it. Read the reservation again and retry.

### A constraint under the lock

The lock is the guarantee; a database constraint can add a net under it, against rows written outside the bundle.
PostgreSQL has one that fits a resource of capacity one, an exclusion constraint on the confirmed spans:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
ALTER TABLE phil_reservation ADD CONSTRAINT phil_reservation_no_overlap
    EXCLUDE USING gist (resource WITH =, tsrange(starts_at, ends_at) WITH &&)
    WHERE (status = 'confirmed');
```

It knows neither capacities above one, nor buffers, nor holds that keep their units for a few minutes, so it refuses
less than the bundle does and never replaces it; and it binds every resource of the table, so add it only when every
resource has a capacity of one. MySQL has no equivalent. The bundle does not create it.

## Moments and holders

The moments — start, end, creation, end of a hold — are columns of DBAL's `datetime_utc_immutable` type: written in UTC
whatever the zone of the moment given, read back in UTC whatever the zone of the process, so that two servers in
different zones see the same instant. They are given back on the resource's clock: `interval()` is a `phil/reservation`
`Interval`. The queries compare their moments with the same type. The holder is an identifier the application chooses —
an account, an order, a session — of 1 to 255 bytes, never a name or an address.

## Events

When a PSR-14 dispatcher is registered as `event_dispatcher`, each change sends one event once it is saved:
`ReservationHeld`, `ReservationConfirmed`, `ReservationRescheduled`, `ReservationCancelled` and `ReservationExpired`.
Each carries the identifiers of the reservation and of its resource only; a listener reads the rest.
