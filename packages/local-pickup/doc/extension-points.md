# Extension points

The library is in its `0.x` releases: the ports, the value objects and the exceptions may change in a minor release,
said in the changelog. They are meant to be stable from `1.0`.

## Ports

| Port | Implemented by | Does |
|---|---|---|
| `Contract\PickupRepository` | a table, with what `Pickup::toArray()` gives | keeps the pickups; gives the bookings of a point, the overdue orders and a customer's pickups |
| `Contract\HandOverLog` | a table | journals the hand-overs; exports and erases the collectors' names |
| `Contract\ReadyNoticeDispatcher` | an adapter to the bus | sends `Message\NotifyPickupReady` with the order number |
| `Contract\ReadyNotifier` | an adapter to the application's notifications | sends the ready notice with its code |
| `Contract\ArrivalNotifier` | an adapter to the staff's screen or notifications, optional | tells the staff of an arrival, once |
| `Contract\ExpiredPickupCanceller` | an adapter to the commerce | cancels and refunds an expired order; idempotent |
| `Contract\ExpiredPickupRestocker` | an adapter to the logistics | puts an expired order's goods back in stock; idempotent |

`Memory\InMemoryPickups` and `Memory\InMemoryHandOverLog` keep their data in arrays, for tests and a single process.

## The port the library offers

`Contract\PickupDesk` is what a counter screen or a point-of-sale register calls to hand an order over; `Counter`
implements it. A register's adapter depends on the interface and passes its own identifier as the station.

## The store

A store keeps the array `Pickup::toArray()` gives — scalars and nulls only, instants as Unix timestamps — and rebuilds
the pickup with `Pickup::fromArray()`, which refuses a row out of shape. Index the order number (unique), the point
with the slot's start and end for `bookings()`, the state with the hold's end for `overdue()`, and the customer.

```php
final readonly class DbalPickups implements PickupRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function find(string $order): ?Pickup
    {
        $row = $this->connection->fetchOne('SELECT data FROM pickup WHERE order_number = ?', [$order]);

        return false === $row ? null : Pickup::fromArray(json_decode($row, true, 8, \JSON_THROW_ON_ERROR));
    }

    // save(), bookings(), overdue() and ofCustomer() read and write the same column.
}
```

## Restocking through the logistics

An application using phil/commerce-logistics-bundle releases the order's reservation, which returns its units to the
stock they were reserved from:

```php
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\LocalPickup\Contract\ExpiredPickupRestocker;
use Phil\LocalPickup\Pickup;

final readonly class ReleaseReservation implements ExpiredPickupRestocker
{
    public function __construct(private Stock $stock, private ClockInterface $clock)
    {
    }

    public function restock(Pickup $pickup): void
    {
        $this->stock->release($pickup->order, $this->clock->now());
    }
}
```

## Settings

| Setting | Where | Default |
|---|---|---|
| The secret of the codes | `PickupCodes` | none: at least 32 bytes |
| The attempt limit | `Counter` | 5, from 1 to 20 |
| What expiry does | `Expiry`, `ExpiryOutcome` | none: chosen by the application |
| The batch of an expiry run | `Expiry::run()` | 100, from 1 to 1,000 |
| Slots, preparation, hold, capacity, hours | `PickupPoint` and its `Bookable` | 30 minutes, 120 minutes, 168 hours |
