# phil/calendar-sync

**External calendar synchronization: busy times read from iCalendar feeds, CalDAV, Google Calendar and Microsoft
Graph, and the site's events written to them, each service an adapter behind a port.**

A site that books people — a game master, a room, a coach — must not offer a time its people already gave elsewhere,
and its bookings should appear in the calendars they read. phil/calendar-sync does both:

- it reads **when** someone is taken from a calendar of theirs, never **what** fills the time: busy periods only, a
  start, an end and whether it is tentative — never a title, a place, a note or a guest;
- it writes the site's events into that calendar, replaced when written again, removed when withdrawn.

Each service is an adapter, named by the connection that uses it: `ics` reads a published iCalendar feed, `caldav` a
CalDAV collection, `google` a Google calendar, `microsoft` an Outlook calendar through Microsoft Graph. An application
adds its own by implementing the same two ports.

The application keeps the connections, their credentials and the busy periods read, behind ports: the package stores
nothing, and keeps nothing between calls. Credentials are asked for at each call and cannot be serialised. The
Messenger messages carry the identifier of a connection, and of an event, never a token, an address or a time read.

Every address is read by `phil/url`. A feed is read through `phil/safe-http`, and the CalDAV client is wrapped in
Symfony's `NoPrivateNetworkHttpClient`: neither reaches a private network, and no redirect carries credentials to
another host. Every read is bounded in time, bytes, components, pages and periods; the iCalendar reader is fuzzed.

phil/calendar-sync is a plain PHP library, on Symfony's HttpClient and Clock and on `phil/recurrence` for the rules of
the events it reads.

## Status

| Part | State |
|---|---|
| `CalendarSync`, the ports, `BusyPeriod`, `BusyPeriods`, `SyncWindow`, `CalendarConnection`, `Credentials`, `OutboundEvent` | Implemented and tested |
| The iCalendar reader — events, rules, added, removed and changed occurrences, free/busy times | Implemented, tested and fuzzed |
| The iCalendar writer | Implemented and tested |
| The adapters `ics`, `caldav`, `google` and `microsoft` | Implemented and tested against HTTP test doubles |
| The messages `RefreshBusyTimes` and `SyncEvent` and their handlers | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and wiring the services. |
| [Synchronizing](doc/synchronizing.md) | Connections, credentials, busy times, events written, messages, failures. |
| [Adapters](doc/adapters.md) | What each adapter reads and writes, its calendar, its credentials and its bounds. |
| [The iCalendar reader](doc/icalendar.md) | What is read of a calendar, the time zones, the bounds and what is refused. |
| [Extension points](doc/extension-points.md) | The ports an application implements, its own adapters, and what stays closed. |
| [Testing](doc/testing.md) | The package's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/calendar-sync
```

```php
use Phil\CalendarSync\Adapter\CalDav;
use Phil\CalendarSync\Adapter\GoogleCalendar;
use Phil\CalendarSync\Adapter\IcsFeed;
use Phil\CalendarSync\Adapter\MicrosoftGraph;
use Phil\CalendarSync\CalendarSync;
use Phil\CalendarSync\SyncWindow;

$writers = [new CalDav(), new GoogleCalendar(), new MicrosoftGraph()];
$sync = new CalendarSync($connections, $credentials, $busyStore, sources: [new IcsFeed(), ...$writers], destinations: $writers);

// The next 60 days of a member's calendar, joined into the fewest periods and given to the store.
$sync->refreshBusy($connectionId, SyncWindow::days(new DateTimeImmutable('today'), 60));

// A booking written into it, then withdrawn.
$sync->publish($connectionId, $booking);
$sync->withdraw($connectionId, $booking->uid);
```

## Requirements

PHP 8.5 or later with `ext-dom`, `ext-intl`, `ext-libxml` and `ext-mbstring`; Symfony 8.2's HttpClient, HTTP
foundation and Clock; `phil/recurrence`, `phil/safe-http` and `phil/url`. Symfony Messenger is optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
