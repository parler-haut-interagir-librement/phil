# Installation

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Installing

```sh
composer require phil/shifts
```

The library has no configuration and no service: an application builds its objects and stores what the formats
write, or uses phil/shifts-bundle, which stores the rosters and the swap board with Doctrine and shows them in Twig.

## A first roster

A fixed shift of two places and a reserve of one, a volunteer who signs up, a third one who waits, a withdrawal that
promotes the reserve, and the minutes served once the shift is over:

```php
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

$zone = new DateTimeZone('America/Toronto');
$shift = new Shift(
    'desk-sat-am',
    'Info desk',
    ShiftKind::Fixed,
    new DateTimeImmutable('2026-11-28 09:00', $zone),
    new DateTimeImmutable('2026-11-28 12:00', $zone),
    capacity: 2,
    reserve: 1,
);

$roster = Roster::open($shift)
    ->signUp('ada', [], new DateTimeImmutable('2026-11-20 10:00', $zone))
    ->signUp('bob', [], new DateTimeImmutable('2026-11-20 10:05', $zone))
    ->signUp('cy', [], new DateTimeImmutable('2026-11-20 10:10', $zone));   // the reserve

$roster = $roster->withdraw('ada', new DateTimeImmutable('2026-11-27 18:00', $zone));
// $roster->changes: VolunteerWithdrew(ada), ReservePromoted(cy)

try {
    $roster->signUp('dee', [], new DateTimeImmutable('2026-11-28 09:30', $zone));
} catch (ShiftRefused $refused) {
    $refused->reason;   // 'shift.closed': the shift has started
}

ServedHours::of('cy', [$roster], new DateTimeImmutable('2026-11-28 12:00', $zone))->minutes;   // 180
```

The instants an application gives are its own clock's; the library reads them in UTC and never asks the system for
the time.
