# phil/waitlist-bundle

**Full sessions, workshops and events lose the places freed by cancellations: a waiting list offers them in turn, with
a deadline, and passes lapsed offers on.**

phil/waitlist-bundle gives anything with a capacity a waiting list: a session, a workshop, an event, a product in short
supply. The application names what the list is for — a kind and an identifier, such as `session` and `S-42` — and keeps
counting its own capacity. Participants join the list and see their position. When places free up, the application
releases them to the list, which offers them to the next entries; each offer is held until a deadline. An entry accepts
it — the application then gives the places —, declines it, or lets it lapse, and the places go to the next entries at
once. Places that fit nobody yet are held until someone fits.

Each request runs under its list's lock, so that two requests never offer the same place; joining twice gives back
the same entry, its rank unchanged. The entries are offered places strictly in order by default, or passing over a
group that needs more, through a policy the application may replace. A list may also be a draw: the entries made
before the draw are ordered by a published seed, so that anyone can check the order, and those who join afterwards wait
behind them. Every change is an event that carries identifiers only; with phil/notifications-bundle, the participant is
told that a place is offered to them, or that their offer lapsed.

It stores identifiers of participants, never their names or addresses.

## Status

| Part | State |
|---|---|
| `Waitlist` and `Entry` in Doctrine, mapped by attributes, moments in UTC | Implemented and tested on SQLite |
| `Waitlists`: open, join, position, release, offer with a deadline, accept, decline, leave, lapse, reclaim, close | Implemented and tested |
| The draw with a published seed | Implemented and tested |
| `FirstInLine`, `FirstThatFits` and the `OfferPolicy` port | Implemented and tested |
| The `phil:waitlist:lapse` command | Implemented and tested |
| The notifications through phil/notifications-bundle, an optional integration | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the schema, the command on a schedule. |
| [Configuration](doc/configuration.md) | The options of `phil_waitlist`. |
| [Waiting lists](doc/waitlists.md) | Opening a list, joining, the position, releasing places, offers and their deadline, the draw, the lock, the events. |
| [Notifications](doc/notifications.md) | Telling participants of their offers through phil/notifications-bundle. |
| [Extension points](doc/extension-points.md) | The ports, events and options an application chooses or replaces. |
| [Testing](doc/testing.md) | The bundle's tests and its mutation bar. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/waitlist-bundle
```

```php
use Phil\WaitlistBundle\Application\Exception\WaitlistRefused;
use Phil\WaitlistBundle\Application\Waitlists;

$waitlist = $waitlists->open('session', $session->id);

try {
    $entry = $waitlists->join($waitlist->id, $accountId, places: 2);
    $waitlists->standing($entry->id)->position; // 1 for the next in line
} catch (WaitlistRefused $refused) {
    $refused->reason; // RefusalReason::Closed, or TooManyPlaces
}

// A registration was cancelled in the application: two places are free.
$waitlists->release($waitlist->id, 2);  // offered to the next entries, each until its deadline

$waitlists->accept($entry->id);         // OfferAccepted: the application gives the places
```

```sh
# Every minute: the offers whose deadline has come lapse, their places go to the next entries.
php bin/console phil:waitlist:lapse
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.8 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
