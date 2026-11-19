# Resources

A `BookableResource` is what can be booked, with what decides its bookings. Each value is checked as it is set, by
`phil/reservation`'s own types: a value the library refuses throws its `InvalidDefinition`, and the resource keeps the
value it had.

```php
$room = BookableResource::create('Salle Verne', capacity: 1, timezone: 'America/Toronto');
$room->buffer(before: 10, after: 15);
$room->restrict(minimumNotice: 120, maximumAdvance: 60 * 24 * 90, minimumDuration: 60, maximumDuration: 240, slotStep: 30);
$room->openOn([1 => [[540, 1260]], 2 => [[540, 1260]], 6 => [[600, 1440]], 7 => [[0, 60]]], closedDays: ['2026-12-25']);
$room->remindBefore(1440, 60);
$room->holdFor(15);
$resources->save($room);
```

| Method | What it sets |
|---|---|
| `create(name, capacity, timezone)` | A name of 1 to 200 characters, the units booked at once, the IANA time zone its hours are read in |
| `rename()`, `resize()`, `moveTo()` | The same, later |
| `buffer(before, after)` | The minutes held before and after each booking; they add up between two bookings |
| `restrict(…)` | The notice, the booking window, the shortest and longest length and the grid, in minutes; `null` leaves one out |
| `openOn(hours, closedDays)` | The weekly hours by ISO weekday, in minutes of the day, and the days closed; `null` opens it at every moment |
| `remindBefore(…)` | The minutes before the start each holder is reminded, at most 20 |
| `holdFor(minutes)` | How long a hold keeps its units, from 1 minute to a day; 15 by default |
| `activate(false)` | Takes no new reservation; those made stay |

The hours follow `WeeklyAvailability` of `phil/reservation`: ranges that touch make one span, across midnight too.
`bookable()` gives the library's `Bookable` built from these values, and `reminderSchedule()` its `ReminderSchedule`.

`claims()` counts the requests that locked the resource's row; see [Reservations](reservations.md#the-lock).

## Buffers between neighbours

The buffers of two bookings add up: with fifteen minutes after each booking and fifteen before each, a booking that
ends at 11:00 leaves the resource free from 11:30, not 11:15. A request is checked with its own buffers against the
spans of the others, theirs included.

## People

A person — a game master, a speaker, a volunteer, a teacher — is a resource like a room or a table: a
`BookableResource` of capacity one, in the person's time zone, whose hours are the times the person gives. Their
bookings follow the same rules: a person booked at one table from 19:00 to 22:00 is refused at another from 21:00, and
a buffer after each booking gives them a pause between two.

```php
$gameMaster = BookableResource::create('Game master 12', capacity: 1, timezone: 'America/Toronto');
$gameMaster->buffer(before: 0, after: 15);
$resources->save($gameMaster);

$reservations->book($gameMaster->id, $start, $end, holder: 'table-4');
```

The resource names the person by an identifier or a role, never by an address; the link between the resource and the
person's account belongs to the application. A session that needs a room and a person books both, in the order the
application chooses, and cancels the first when the second is refused.
