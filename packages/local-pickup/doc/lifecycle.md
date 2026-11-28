# The life of a pickup

## The states

| State | Reached by | Holds a place in its slot | Has a code |
|---|---|---|---|
| `Preparing` | `Pickups::place()` | Yes | No |
| `Ready` | `Pickups::ready()` | Yes | Once the notice is sent |
| `PickedUp` | `Counter::handOver()` | No | No: spent |
| `Expired` | `Expiry::run()` | No | No: spent |

Any other move throws `PickupRefused`, whose `reason` is `state_<state>`.

## Placing

```php
$pickup = $pickups->place('WEB-1042', 'customer-7', $point, $slot, new DateTimeImmutable(), collector: 'Élise Tremblay');
```

The order number is up to 64 letters, digits, `_`, `.`, `#`, `/` or `-`; it never holds `:`. The customer is the
opaque identifier the application's privacy requests name people by. The collector is the person the customer
designates to collect in their place, if any. Placing refuses a point that takes no order (`point_closed`), an order
that has a pickup already (`already_placed`) and a slot with any reason (`slot`).

## Ready, and the notice

```php
$pickups->ready('WEB-1042', new DateTimeImmutable());
```

The pickup is ready and held until the agreed hold period has run, counted from its slot's start, or from now when it
is ready later than that. `ready()` saves it and dispatches the order number through `Contract\ReadyNoticeDispatcher`
— a `Message\NotifyPickupReady` on the application's bus.

`Message\NotifyPickupReadyHandler` runs in the worker. It reads the pickup again; when it is ready and its notice not
sent, it issues a code, saves the code's digest, sends a `ReadyNotice` through `Contract\ReadyNotifier`, then marks the
notice sent. The notice holds the agreed point, the slot, the end of the hold, the designated collector and the
`IssuedCode`, to show as text with `display()` and as a QR code with `svg()`. The code exists only there.

- A notice that fails reaches the bus, which retries; the retry issues a new code, which replaces the digest.
- A message delivered twice finds the notice sent and sends nothing.
- A message for a pickup no longer ready, or unknown, does nothing.

`Pickups::resend()` forgets the current code and dispatches the notice again: after the code was locked by wrong
attempts, or when the customer lost it.

## Arrival

```php
$first = $pickups->arrive('WEB-1042', ArrivalSource::Manual, new DateTimeImmutable());
```

The application may signal an arrival on its own (`Automatic`), from a location the customer shares, and the customer
may press "I am here" on the order (`Manual`). The first signal is kept with its source and returns `true`; the staff
is told through `Contract\ArrivalNotifier`, once. A later signal returns `false` and tells nobody. When the customer
withdraws a permission after ordering — the location, notifications —, the manual signal still works and the pickup
goes on: no step after placing reads a permission.

## The hold period and expiry

```php
$expiry = new Expiry($pickupRepository, ExpiryOutcome::Restock, restocker: $restocker);
$expired = $expiry->run(new DateTimeImmutable(), limit: 100);
```

A ready pickup is overdue from the end of its hold period. `Expiry::run()` reads at most `limit` overdue pickups (1 to
1,000), oldest end first, and for each calls the port the configuration chose:

| `ExpiryOutcome` | Port | Does |
|---|---|---|
| `CancelAndRefund` | `Contract\ExpiredPickupCanceller` | The application's commerce cancels and refunds the order. |
| `Restock` | `Contract\ExpiredPickupRestocker` | The application's logistics puts the goods back in stock at the agreed stock location, such as by releasing the order's reservation; the order stays. |

The constructor refuses an outcome without its port. The port is called before the pickup is saved as expired, so a run
stopped in between calls it again on the next run: both ports are idempotent, keyed on the order number. Run it from a
schedule, every few minutes.
