# Free slots

```php
$slots = $bookable->slots($period, minutes: 60, bookings: $bookings, now: $now, quantity: 1, step: 30, limit: 100);
```

`slots()` gives, in order, the slots of the period a request asked for at `$now` would get: inside one of the
resource's open spans and inside the period, following every rule, with enough units free, buffers included. Each slot
is an `Interval` on the resource's clock.

| Argument | Meaning |
|---|---|
| `$minutes` | The length of a slot |
| `$quantity` | The units each slot must have free; outside 1 to the capacity, no slot |
| `$step` | The minutes between two starts, the length of a slot by default |
| `$limit` | The most slots given, from 1 to `Bookable::MAX_SLOTS` (1,000) |

Starts go by the step from midnight on the resource's clock, and start anew at each midnight: with a step of 30, a
period that opens at 9:10 offers 9:30 first; with a step of 7 hours, 0:00, 7:00, 14:00, 21:00, then 0:00 again. A step
that divides a day keeps one grid from day to day.

The walk reads at most `Bookable::MAX_CANDIDATES` (10,000) starts in one call and returns what it found: a resource
booked solid over a long period costs a bounded time. A length, a step or a limit out of bounds throws
`InvalidDefinition`.

## Units free

```php
$bookable->free($interval, $bookings);          // 0 to the capacity
$bookable->free($interval, $bookings, 'b-1');   // as if b-1 were moved away
```

`free()` gives the units still free over the whole interval, buffers included: what a page shows next to a slot, or
the most seats a form offers.
