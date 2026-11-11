# Resources

A `Bookable` is what can be booked, as the library sees it: no name, no identifier, only what decides a booking.

| Property | Default | Meaning |
|---|---|---|
| `capacity` | `1` | The units booked at once: 1 for a room or a person, 8 for the seats of a table, 12 for a set of chairs |
| `bufferBefore` | `0` | Minutes held before each booking: setting up |
| `bufferAfter` | `0` | Minutes held after each booking: clearing away, cleaning |
| `availability` | `AlwaysAvailable` | When it can be booked, an `Availability` |
| `rules` | `[]` | The `Rule`s every request follows |
| `timezone` | UTC | The clock its hours and its grid are read on |

A capacity below one or a negative buffer throws `InvalidDefinition`.

## Buffers

A booking holds its units from its buffer before its start to its buffer after its end. Buffers add up: with fifteen
minutes after and fifteen before, the booking that follows one ending at 10:00 starts at 10:30 at the earliest. They
count against the capacity like the booking itself.

## Hours

`AlwaysAvailable` opens the resource at every moment. `WeeklyAvailability` opens it by weekday on its own clock:

```php
new WeeklyAvailability(
    [
        1 => [[540, 1020]],             // Monday, 9:00 to 17:00
        5 => [[540, 720], [780, 1440]], // Friday, 9:00 to 12:00 and 13:00 to midnight
        6 => [[0, 120]],                // Saturday, midnight to 2:00
    ],
    new DateTimeZone('America/Toronto'),
    closed: ['2026-12-25'],
);
```

Weekdays go from 1 for Monday to 7 for Sunday, as ISO 8601 numbers them, and ranges from a minute of the day to a
later one, up to 1440 for midnight at the end of the day. Ranges of a day do not overlap; ranges that touch make one
span, across midnight too: the Friday evening above runs into Saturday until 2:00, and a booking from 23:00 to 1:00
fits. A day closed has no hours, whatever its weekday. On a day the clock changes, a time that does not exist is read
as the time after the change, so the span keeps its wall-clock ends and lasts an hour less or more.

The hours are read over 1,000 days at most at once; a longer period throws `InvalidInterval`.

## Rules

| Rule | Refuses |
|---|---|
| `MinimumNotice(minutes)` | A booking that starts sooner after it is asked for; at zero, a booking in the past |
| `MaximumAdvance(minutes)` | A booking that starts further ahead: a booking window that slides |
| `DurationLimits(minimum, maximum)` | A booking shorter than the shortest or longer than the longest, without a longest by default |
| `SlotGrid(minutes)` | A booking that does not start on a multiple of the step from midnight, or does not last a multiple of it |

The grid's step divides a day: 15, 30, 60, 90, 120… An application adds its own rules by implementing `Rule`: see
[Extension points](extension-points.md).
