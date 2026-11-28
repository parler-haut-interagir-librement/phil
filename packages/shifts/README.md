# phil/shifts

**Volunteer shifts without double bookings: fixed, task or duration shifts, sign-up, assignment or allocation by interest, a reserve and a swap board.**

phil/shifts is a plain PHP library. It holds the **shifts** of an event and the **roster** of each: who holds a place,
who waits in the reserve, who is interested. A shift asks a volunteer to be there from its start to its end
(**fixed**), to do a **task** some time within its window, or to serve a **duration** when they choose within it.

Its places are given in one of **three modes**: volunteers **sign up** themselves, first come, first served; a
coordinator **assigns** each place; or volunteers say they are **interested** and the places are **allocated** among
them — by a coordinator, or by an allocator that gives them first to those who hold the fewest minutes. A coordinator
may assign a place in every mode. A full shift fills its **reserve**, and a place freed goes to the first volunteer of
the reserve.

A shift may require a **role** — first aid, cash —, be **critical**, so that a screen shows it first while it lacks
volunteers, and **weigh** its minutes — a night shift counted 150 %. Two fixed shifts that overlap, or come closer
than the rest asked between them, are told apart before a volunteer takes both.

A volunteer who cannot come puts their place on the **swap board**; another one takes it, at once, or once a
coordinator approves when the shift asks for it. An offer whose volunteer no longer holds the place is stale, and
nobody takes it.

Rosters and offers are **immutable**: each operation returns the next one with the **events** of the change, which
carry identifiers only, or refuses with a reason a screen translates. The **minutes served**, plain and weighted, are
read from the rosters of the shifts that are over. The shifts of an event travel in the JSON format `phil-shifts/1`,
read within bounds and written back the same.

## Status

| Part | State |
|---|---|
| Shifts: fixed, task, duration; places, reserve, role, critical, weight | Implemented and tested |
| Rosters: sign-up, assignment, interest, withdrawal and promotion from the reserve | Implemented and tested |
| `FairAllocator`, `Conflicts` and `ServedHours` | Implemented and tested |
| The swap board: offer, take, approve, refuse, cancel, stale offers | Implemented and tested |
| The format `phil-shifts/1` and the stored rosters and offers | Implemented and tested |
| Recurring shifts, a printable certificate of hours | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and a first roster from sign-up to hours served. |
| [Shifts and rosters](doc/shifts-and-rosters.md) | The kinds, the modes, the reserve, the conflicts, the allocation and the hours. |
| [Swap board](doc/swap-board.md) | Offering a place, taking it, the coordinator's approval, stale offers. |
| [Plan format](doc/plan-format.md) | The JSON format `phil-shifts/1`, its bounds, and the stored rosters and offers. |
| [Extension points](doc/extension-points.md) | The allocator, the storage, the events, and what is stable. |
| [Testing](doc/testing.md) | The tests, the fuzz target and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/shifts
```

```php
use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;
use Phil\Shifts\Shift\ShiftKind;

$shift = new Shift('desk-sat-am', 'Info desk', ShiftKind::Fixed, new DateTimeImmutable('2026-11-28 09:00', $zone), new DateTimeImmutable('2026-11-28 12:00', $zone), capacity: 2, reserve: 3);
$roster = Roster::open($shift)->signUp('ada', $roles, $now);   // a place, or the reserve once the shift is full
$roster = $roster->withdraw('ada', $later);                   // the first of the reserve takes the place
$hours = ServedHours::of('bob', [$roster], $afterTheEvent);   // 180 minutes, 180 weighted
```

## Requirements

PHP 8.5 or later, with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
