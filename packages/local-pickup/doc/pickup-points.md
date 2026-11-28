# Pickup points and slots

## A pickup point

```php
use Phil\LocalPickup\PickupPoint;
use Phil\LocalPickup\PickupPointKind;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Rule\MaximumAdvance;

$zone = new DateTimeZone('America/Toronto');
$booth = new PickupPoint(
    code: 'booth-hall-b',
    name: 'Booth 214, Hall B',
    kind: PickupPointKind::ConventionBooth,
    stockLocation: 'convention-stock',
    address: "Convention centre\nHall B, booth 214",
    slots: new Bookable(
        capacity: 6,
        availability: new WeeklyAvailability([5 => [[600, 1140]], 6 => [[540, 1140]], 7 => [[540, 1020]]], $zone),
        rules: [new MaximumAdvance(60 * 24 * 30)],
        timezone: $zone,
    ),
    slotMinutes: 15,
    preparationMinutes: 60,
    holdHours: 48,
);
```

| Field | Meaning | Bounds |
|---|---|---|
| `code` | The point's identifier. | Up to 64 lowercase letters, digits, `_`, `.` or `-`. |
| `name`, `address` | What the notice and the receipt show; the address may hold line breaks. | 1 to 500 bytes of UTF-8, no other control character. |
| `kind` | `Store` or `ConventionBooth`. | |
| `stockLocation` | The code of the stock location the goods are taken from, as the application's logistics names it. | As `code`. |
| `slots` | A phil/reservation `Bookable`: its capacity is the number of orders a slot takes; its hours, closed days, buffers and rules are when customers may come; its time zone is the point's clock. | |
| `slotMinutes` | The length of a slot. | 1 to 1,440. |
| `preparationMinutes` | The time an order needs before it is ready: no slot starts sooner after the order is placed. | 0 to 43,200 (thirty days). |
| `holdHours` | How long a ready order waits at the counter. | 1 to 2,160 (ninety days). |
| `accepting` | Whether the point takes new orders. | |

A point is configuration: the application builds it from its own records, and the library never stores it.

## The agreed point

Placing an order copies the point onto the pickup as an `AgreedPickupPoint`: its code, name, kind, stock location,
address, time zone and hold period. The notice, the receipt and the expiry read this copy. When the merchant renames the
point, moves it, shortens its hold period, or stops taking orders there — closed for the day, or no longer allowed to
sell from it —, the orders already placed keep the counter they were promised and keep being prepared, announced and
handed over. Only `Pickups::place()` reads `accepting`.

## Slots

`PickupSlots::available()` gives the slots of a period a customer may choose now:

```php
$week = Interval::ofTimestamps($monday->getTimestamp(), $monday->modify('+7 days')->getTimestamp(), $zone);
$offered = $pickupSlots->available($booth, $week, new DateTimeImmutable(), limit: 100);
```

Each slot is open, follows the point's rules, has a place left among the pickups being prepared or ready, and starts
no sooner than the preparation time after now. The pickups collected or expired free their place. Starts follow
phil/reservation's grid: every `slotMinutes` from midnight on the point's clock.

`PickupSlots::check()` gives the phil/reservation `Decision` of one slot: its reasons — `Full`, `Unavailable`, the
rules' own — with the orders it meets as conflicts, and `TooSoon` with `['minutes' => preparationMinutes]` when the
order could not be ready by its start. `Pickups::place()` refuses a slot with any reason, and the `PickupRefused` it
throws carries the violations.

Two customers taking the last place at once both read a free place: the application puts them one after the other
with a lock on the point around `Pickups::place()`, such as one of symfony/lock keyed on the point's code.
