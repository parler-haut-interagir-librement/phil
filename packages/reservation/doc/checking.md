# Checking a request

```php
$decision = $bookable->check(new BookingRequest($interval, $now, quantity: 2, replacing: null), $bookings);
```

`BookingRequest` holds the span asked for, the moment it is asked — the notice and the booking window count from it —,
the units asked for, and the identifier of the booking it replaces when an existing booking is moved or resized: that
booking is left out of the conflicts. `$bookings` are the `Booking`s that hold units: an application passes the
confirmed ones and the holds not expired, those of the period at least.

## The decision

`Decision::allowed()` is true when nothing refuses the request. Otherwise `violations` lists every reason, in this
order: the quantity, the rules in their order, the hours, the capacity. Each `Violation` carries a `ViolationCode`
whose string value is stable, to keep and translate, and the numbers its message needs:

| Code | Value | Parameters |
|---|---|---|
| `Quantity` | `quantity` | `max`: the capacity |
| `TooSoon` | `too_soon` | `minutes`: the notice |
| `TooFarAhead` | `too_far_ahead` | `minutes`: the booking window |
| `TooShort` | `too_short` | `minutes`: the shortest length |
| `TooLong` | `too_long` | `minutes`: the longest length |
| `OffGrid` | `off_grid` | `minutes`: the step |
| `Unavailable` | `unavailable` | none |
| `Full` | `full` | `free`: the units free over the whole span |

A quantity below one or above the capacity is refused with `Quantity` only, without `Full`.

## Conflicts

`Decision::conflicts` lists the identifiers of the bookings whose span, buffers included, meets the request's, whether
the request is refused or not: a table of eight with three seats taken accepts four more and still names the booking
it shares the evening with. An application shows them, or asks before moving them.

## The capacity

The units held at each moment come from the bookings' spans, laid out once by `LoadProfile`; the request is full when
the most units held at once over its span, buffers included, leave fewer than it asks for. Two bookings that only touch
never hold units at the same moment.

## Checking and saving

`check()` only reads the bookings it is given: it does not hold them while the application saves the new one. Two
requests for the last free unit at the same instant may both be allowed, and both saved, beyond the capacity. The
application serialises the check and the write for one resource: a lock on it — Symfony Lock, or `SELECT … FOR
UPDATE` on its row — held while the bookings are loaded, checked and the booking written; or a constraint in the
store that refuses an overlap beyond the capacity, a failed write then answered as `Full`.
