# phil/reservation

**The core of bookings: a resource, its slots, its rules, its conflicts and its reminders.**

phil/reservation decides whether something can be booked. A resource — a room, a table and its seats, a person's
time, a piece of equipment, a bed, a game — has a capacity, buffers around each booking for setting up and clearing
away, the hours it is open and the rules a booking follows: a notice, a booking window, a shortest and a longest
length, a grid of starts. A request is checked against the bookings already made and gets every reason it is refused,
with the bookings it meets. The same resource lists the free slots of a period, and a reminder schedule says which
reminder of a booking is due.

It is for any PHP application that books something: rooms and tables directly, appointments, equipment rental,
lodging, a game library's loans or escape-room sessions as thin uses on top. The library
depends on nothing but PHP and `ext-mbstring`; it keeps no state and stores nothing. In a Symfony application,
`phil/reservation-bundle` keeps resources and reservations in Doctrine, holds and confirms them one request at a time
per resource, sends the reminders and shows the bookings in the calendar.

## Status

| Part | State |
|---|---|
| `Interval`, `Booking`, `BookingRequest`, `Decision`, `Violation` | Implemented and tested |
| `Bookable`: checking a request, units free, free slots | Implemented and tested |
| Rules: notice, booking window, lengths, grid | Implemented and tested |
| Availability: always open, weekly hours with days closed | Implemented and tested |
| `ReminderSchedule` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Resources](doc/resources.md) | `Bookable`: capacity, buffers, hours, rules, time zone. |
| [Checking a request](doc/checking.md) | `BookingRequest`, `Decision`, the reasons a booking is refused, conflicts. |
| [Free slots](doc/slots.md) | `slots()` and `free()`: steps, quantity, bounds. |
| [Reminders](doc/reminders.md) | `ReminderSchedule`: offsets, the reminder due, reminders overtaken. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests, mutation bar and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/reservation
```

```php
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Booking;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Interval;
use Phil\Reservation\Rule\MinimumNotice;
use Phil\Reservation\Rule\SlotGrid;

$zone = new DateTimeZone('America/Toronto');
$room = new Bookable(
    capacity: 1,
    bufferAfter: 15,
    availability: new WeeklyAvailability([1 => [[540, 1020]], 2 => [[540, 1020]]], $zone),
    rules: [new MinimumNotice(120), new SlotGrid(30)],
    timezone: $zone,
);

$booked = [new Booking('b-1', new Interval(new DateTimeImmutable('2026-11-10 10:00', $zone), new DateTimeImmutable('2026-11-10 11:00', $zone)))];
$asked = new Interval(new DateTimeImmutable('2026-11-10 11:00', $zone), new DateTimeImmutable('2026-11-10 12:00', $zone));

$decision = $room->check(new BookingRequest($asked, new DateTimeImmutable()), $booked);
$decision->allowed();          // false: the buffer after b-1 holds the room until 11:15
$decision->violations[0]->code; // ViolationCode::Full
$decision->conflicts;           // ['b-1']

$room->slots(new Interval(new DateTimeImmutable('2026-11-10 09:00', $zone), new DateTimeImmutable('2026-11-10 17:00', $zone)), 60, $booked, new DateTimeImmutable());
```

## Requirements

PHP 8.5 or later with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
