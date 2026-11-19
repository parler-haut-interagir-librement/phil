# phil/reservation-bundle

**Reservations for Symfony: resources and their reservations in Doctrine, booked one request at a time per resource.**

phil/reservation-bundle keeps bookable resources and their reservations in Doctrine, and books them with the rules,
buffers, hours and conflicts of `phil/reservation`. A request is held for a few minutes or booked at once; it runs
under its resource's lock, so that two requests never take the last unit together. A hold is confirmed or expires;
a reservation is moved, resized or cancelled; the free slots of a period are listed. The reservation form gives every
reason a request would be refused, in the reader's language. The holds that expire and the reminders due are handled
by two commands, the reminders going out as Messenger messages, and the confirmed reservations appear in the views of
`phil/calendar-bundle`.

It is the core of every booking an application offers: rooms, tables and people directly, and appointments, equipment
rental, lodging, a game library's loans or escape-room sessions as thin uses on top. It stores identifiers of
holders, never their names or addresses.

## Status

| Part | State |
|---|---|
| `BookableResource` and `Reservation` in Doctrine, moments in UTC | Implemented and tested, on SQLite, MySQL 8.4 and PostgreSQL 17 |
| People booked as resources, like rooms and tables | Implemented and tested |
| The resource's lock under concurrent requests | Tested with processes racing for the last unit on MySQL and PostgreSQL |
| `Reservations`: check, hold, book, confirm, reschedule, cancel, slots, expired holds | Implemented and tested |
| `Reminders` and the Messenger sender | Implemented and tested |
| `ReservationType` and the messages of the reasons, in English and French | Implemented and tested |
| The commands, the calendar's item source | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the schema. |
| [Configuration](doc/configuration.md) | The options of `phil_reservation`. |
| [Resources](doc/resources.md) | `BookableResource`: capacity, buffers, rules, hours, reminders, holds, people. |
| [Reservations](doc/reservations.md) | `Reservations`: holding, booking, confirming, moving, cancelling, the lock, the events. |
| [Forms and messages](doc/forms.md) | `ReservationType` and `ViolationMessages`. |
| [Holds and reminders](doc/reminders.md) | The commands, `Reminders`, the Messenger message. |
| [The calendar](doc/calendar.md) | The confirmed reservations in `phil/calendar-bundle`'s views. |
| [Extension points](doc/extension-points.md) | The ports, events and options an application chooses or replaces, and the uses built on the bundle. |
| [Testing](doc/testing.md) | The bundle's tests, its integration suite on MySQL and PostgreSQL, and its mutation bar. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/reservation-bundle
```

```php
use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;

public function book(Reservations $reservations, ViolationMessages $messages, string $tableId): Response
{
    try {
        $hold = $reservations->hold($tableId, new DateTimeImmutable('2026-11-10 19:00'), new DateTimeImmutable('2026-11-10 22:00'), 4, $accountId);
    } catch (ReservationRefused $refused) {
        return $this->render('booking/refused.html.twig', ['reasons' => $messages->messages($refused->decision)]);
    }

    // … payment, then:
    $reservations->confirm($hold->id);
}
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5 or later. `symfony/console`, `symfony/messenger` and
`phil/calendar-bundle` are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
