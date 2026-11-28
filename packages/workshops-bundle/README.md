# phil/workshops-bundle

**Workshops for Symfony: sessions with a set number of seats, registrations that never overbook, and a waiting list
that offers the freed seats in turn.**

phil/workshops-bundle keeps workshops, the sessions each is given and the registrations to them in Doctrine. A session
has a span on the clock of its own time zone, a capacity and a window in which it takes registrations. A participant
registers for one seat or several: the registration is confirmed when the seats are free and nobody waiting claims
them, and refused as full otherwise. Each registration runs under its session's lock, so that two requests never take
the last seat together; registering twice gives back the same registration. With phil/waitlist-bundle, each full
session has a waiting list: the seats freed by a cancellation or more capacity are offered in turn, each offer held
until a deadline, and an offer accepted becomes a confirmed registration. The reasons a registration is refused come in
the reader's language, and every change is an event that carries identifiers only.

It is for any application that runs classes, workshops or sessions with limited places: painting, cooking, a
workshop at a convention, a course given several times. It stores identifiers of participants, never their names or
addresses. What a workshop adds — a description, a price, the material, an age — stays in the application's own
tables, by the workshop's identifier.

## Status

| Part | State |
|---|---|
| `Workshop`, `WorkshopSession` and `Registration` in Doctrine, moments in UTC | Implemented and tested on SQLite |
| `Workshops`: schedule, move, resize, cancel a session; register and cancel under the session's lock | Implemented and tested |
| A waiting list per session through phil/waitlist-bundle, `SessionWaitlists`, and the `SeatClaims` port | Implemented and tested on SQLite |
| The refusals and the statuses in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the schema. |
| [Configuration](doc/configuration.md) | The options of `phil_workshops`. |
| [Sessions and registrations](doc/registrations.md) | Scheduling, the registration window, registering, the waiting list of phil/waitlist-bundle, cancelling, the lock, the events. |
| [Extension points](doc/extension-points.md) | The ports, events, messages and options an application chooses or replaces. |
| [Testing](doc/testing.md) | The bundle's tests and its mutation bar. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/workshops-bundle
```

```php
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Application\Workshops;
use Phil\WorkshopsBundle\Infrastructure\Translation\RefusalMessages;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;

$workshop = $workshops->createWorkshop('Painting miniatures');
$session = $workshops->schedule(
    $workshop->id,
    new DateTimeImmutable('2026-12-05 13:00', new DateTimeZone('America/Toronto')),
    new DateTimeImmutable('2026-12-05 16:00', new DateTimeZone('America/Toronto')),
    'America/Toronto',
    capacity: 8,
);

try {
    $registration = $workshops->register($session->id, $accountId, seats: 2); // confirmed
} catch (RegistrationRefused $refused) {
    $message = $messages->message($refused); // "This session is full.", in the reader's language

    // With phil/waitlist-bundle: a place in line, offered the seats that free up.
    $entry = $sessionWaitlists->join($session->id, $accountId, seats: 2);
}

$workshops->seats($session->id); // SeatCount: capacity, taken, free, claimed, available
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.8 or later; phil/waitlist-bundle for the waiting lists.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
