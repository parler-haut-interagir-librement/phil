# phil/incident-mode

**A switch for a bad day: suspend sign-ups, invitations, new messages or everything but reading, for a duration that
is always set, lifted by hand or on its own at the end.**

phil/incident-mode lets a team react to a spam wave, a raid or a security incident without a deployment. An incident
is started from a named preset — suspend sign-ups, suspend invitations, suspend new messages, suspend every new
contribution, read only — for a duration the preset bounds: there is no incident without an end. While it is in force,
the application asks `isSuspended('signups')` before it lets someone sign up, and shows a notice; at its end the
incident stops applying by itself, and the automatic lifting records it in the journal. Someone may lift it before, or
extend it within what its preset allows. Every start, extension and lifting sends an event, and the journal keeps who
started and who lifted each incident, by a stable identifier, never an e-mail address.

It is for any PHP application with members: a community, a forum, a site with sign-ups or invitations, a messaging
service. The application adds its own presets and its own restrictions — `new-listings`, `ticket-sales` — beside the
built-in ones; `read-only` covers them all.

It is a plain PHP library whose requirements are `psr/clock` and `psr/event-dispatcher`. Activations live in a store:
in memory for tests, or in one table of a Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or SQLite, its moments
in UTC, where lifting and extending are conditional updates, so that two workers lifting the same incident send one
event. The lifting at the end is a delayed Symfony Messenger message, with a recurring sweep for the messages a
transport lost. Nothing is kept between calls: a long-running worker sees an incident end at its end.

`phil/moderation-bundle` offers the incident mode in its back office when this library is installed: it wires the
services, the table and the lifting, and proposes an incident when reports surge.

## Status

| Part | State |
|---|---|
| Restrictions, the built-in ones and the application's, `read-only` covering every one | Implemented and tested |
| Presets with a proposed and a longest duration; five built-in presets; the application's added, replacing or leaving out | Implemented and tested |
| Starting with a required duration, extending within the preset, lifting by hand | Implemented and tested |
| The end applying by itself; the automatic lifting recorded once, by a delayed message or a sweep | Implemented and tested |
| `isSuspended()`, `suspended()`, `inForceNow()`, the journal | Implemented and tested |
| Events `IncidentStarted`, `IncidentExtended` and `IncidentLifted` | Implemented and tested |
| In-memory store; DBAL store with conditional updates, moments in UTC, schema and ORM listener | Implemented and tested |
| Actors found and forgotten, for the export and the erasure of personal data | Implemented and tested |
| A page, a banner and a back office | Implemented in `phil/moderation-bundle` |
| An incident scheduled to start later | Not started: an incident starts when it is started |
| Restrictions that spare established members | Not started: the application checks the member's standing before it asks |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, declaring the services in Symfony. |
| [Incidents](doc/incidents.md) | Restrictions, presets, starting, checking, extending, lifting, the journal and the events. |
| [Automatic lifting](doc/automatic-lifting.md) | The end of an incident, the delayed message and the recurring sweep. |
| [Storage](doc/storage.md) | The stores, the table, concurrency, the schema listener, personal data. |
| [Extension points](doc/extension-points.md) | The ports, presets, restrictions and events an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests, its benchmark, and how to test code that checks a restriction. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/incident-mode
```

```php
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\Presets;
use Symfony\Component\Clock\NativeClock;

$incidents = new IncidentMode(Presets::defaults(), new InMemoryActivationStore(), new NativeClock());

$incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(2), Actor::of('8d2e…'), 'Spam wave');

if ($incidents->isSuspended('signups')) {
    // Show the notice instead of the sign-up form.
}
```

## Requirements

PHP 8.5 or later with `ext-sodium`; `psr/clock` and `psr/event-dispatcher`. `doctrine/dbal` for the database store,
`doctrine/orm` for the schema listener and `symfony/messenger` for the delayed lifting, when they are used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
