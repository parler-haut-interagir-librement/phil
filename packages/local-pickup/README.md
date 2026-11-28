# phil/local-pickup

**Let customers collect orders at a store or convention booth: slots with capacity, ready notices, one-time counter
codes with QR, hold periods and expiry.**

phil/local-pickup is a plain PHP library. It takes an order from the moment the customer chooses where and when to
collect it to the moment the staff hands it over, or the hold period ends.

**Pickup points.** A `PickupPoint` is a store's counter or a convention booth, tied to the stock location its goods are
taken from. Its slots are those of a [phil/reservation](https://packagist.org/packages/phil/reservation) resource:
the capacity is the number of orders a slot takes, the hours and rules are when customers may come. An order is ready
no sooner than the point's preparation time.

**The agreed point stays.** Placing an order copies the point onto it — name, address, stock location, time zone and
hold period. A point that changes, closes or is no longer allowed afterwards changes nothing for the orders already
placed: only placing reads whether a point takes orders.

**Four states.** A pickup is being prepared, ready, picked up or expired. Marking it ready dispatches a message that
carries the order number and nothing else; the worker reads the pickup again, issues its code and sends the "ready for
pickup" notice through the application's notifier.

**A short, single-use code.** Eight characters of Crockford's base 32, drawn at random, shown as `7KQ2-M9XD` and as a
QR code drawn by [phil/qr-code](https://packagist.org/packages/phil/qr-code). Only its HMAC-SHA-256 digest, keyed by
a key derived from the secret for this use alone and bound to the order, is kept. The counter checks it in constant
time, counts wrong attempts and locks the code at the limit until a new one is sent. A right code is spent at once.

**Someone else may collect.** The customer may designate a person; the staff checks them, and the journal of hand-overs
keeps who collected, with the designated name beside it. `PickupPersonalData` exports and erases those names for an
access or erasure request.

**Arrival without permissions.** The application may announce an arrival on its own — a shared location — and the
customer may press "I am here" on the order. The first signal counts, and the staff is told once, whatever permission
the customer later withdraws.

**Expiry by configuration.** `Expiry` ends the pickups nobody collected within their hold period, and either cancels
and refunds the order or puts its goods back in stock, through a port the application implements.

**One port for counters and registers.** `Counter` implements `PickupDesk`, the port a counter screen and a
point-of-sale register both call, so that a booth's register marks a pickup as the store's counter does.

The library reads and writes only through ports — the pickups, the journal, the bus, the notifiers, the cancellation and
the restocking — and keeps no state between calls, so its services are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Pickup points, the agreed copy kept on the order, slots with capacity and preparation time | Implemented and tested |
| States, the hold period, the stored row read back | Implemented, tested and fuzzed |
| The ready notice from a worker, by a message carrying the order number only | Implemented and tested |
| Codes: random, HMAC digest only, QR payload, attempt limit, single use | Implemented, tested and fuzzed |
| The counter and the `PickupDesk` port, the journal of hand-overs, third parties | Implemented and tested |
| Arrival signals, automatic or manual, announced once | Implemented and tested |
| Expiry: cancel and refund, or restock | Implemented and tested |
| Export and erasure of the collectors' names | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Pickup points and slots](doc/pickup-points.md) | `PickupPoint`, the agreed copy, slots, capacity and preparation time. |
| [The life of a pickup](doc/lifecycle.md) | Placing, the states, the ready notice, arrivals, the hold period and expiry. |
| [Codes and the counter](doc/counter.md) | Codes, QR codes, the counter, attempts, third parties and registers. |
| [Security and privacy](doc/security.md) | The secret, what is stored, the limits, and the personal data. |
| [Extension points](doc/extension-points.md) | The ports an application implements, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, fuzzing and benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/local-pickup
```

```php
use Phil\LocalPickup\Counter;
use Phil\LocalPickup\HandOverRequest;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\LocalPickup\Pickups;
use Phil\LocalPickup\PickupSlots;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;

$zone = new DateTimeZone('America/Toronto');
$point = new PickupPoint(
    'store-main', 'Main store', PickupPointKind::Store, 'main-backroom', "12 Rue Principale\nMontréal",
    new Bookable(capacity: 4, availability: new WeeklyAvailability(array_fill_keys(range(1, 7), [[600, 1080]]), $zone), timezone: $zone),
    slotMinutes: 30, preparationMinutes: 120, holdHours: 72,
);

$slots = new PickupSlots($pickupRepository);
$pickups = new Pickups($pickupRepository, $slots, $readyNoticeDispatcher);

$offered = $slots->available($point, $week, new DateTimeImmutable());
$pickups->place('WEB-1042', 'customer-7', $point, $offered[0], new DateTimeImmutable());
$pickups->ready('WEB-1042', new DateTimeImmutable());   // the worker sends the notice with the code

$counter = new Counter($pickupRepository, new PickupCodes($secret), $handOverLog);
$counter->handOver(HandOverRequest::scanned($scannedPayload, 'counter-1', 'staff-3', new DateTimeImmutable()));
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
