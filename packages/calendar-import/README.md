# phil/calendar-import

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/calendar-import/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/calendar-import)

**Keep a calendar in step with external iCalendar feeds: read by URL on a schedule, changes found,
the last good state kept when a feed fails, editor edits kept.**

phil/calendar-import is a plain PHP library. It reads iCalendar feeds (RFC 5545) strictly and within bounds, through
`phil/safe-http`, and tells what changed since the last read: events added, changed and removed. A feed that fails
keeps its last good state; an editor's changes to an imported event survive every read. What comes out are neutral
drafts, marked with their source and their UID, that an application turns into its own calendar's events.

## Status

| Part | State |
|---|---|
| `ICalendarReader`, `ReaderLimits`, `Rejection` | Implemented and tested |
| `EventSource`, `IcsUrlSource`, `CalendarSource`, `FetchValidators` | Implemented and tested |
| `Snapshot`, `SnapshotDiff`, `SourceState`, `RefreshPolicy` | Implemented and tested |
| `EditorOverride`, `CalendarImporter`, `ImportedDraft` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reading iCalendar](doc/reading.md) | What the reader accepts and refuses, time zones, bounds. |
| [Sources](doc/sources.md) | The `EventSource` port, the feed read by URL, conditional reads, security. |
| [Refreshing a source](doc/refreshing.md) | Snapshots, differences, failures, the refresh policy, editor overrides, drafts. |
| [Extension points](doc/extension-points.md) | The ports, and the adapter that writes drafts to a calendar. |
| [Testing](doc/testing.md) | The package's tests, its fuzz target and its benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/calendar-import
```

```php
use Phil\CalendarImport\CalendarImporter;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\IcsUrlSource;
use Symfony\Component\Clock\NativeClock;

$importer = new CalendarImporter(IcsUrlSource::create(), $stateStore, $overrideStore, new NativeClock());
$outcome = $importer->refresh(CalendarSource::of('library-events', 'webcal://calendar.example.org/public.ics', 'America/Toronto'));

foreach ($outcome->changes as $change) {
    // $change->kind: Added, Changed or Removed; $change->draft: an ImportedDraft
}
```

`$stateStore` and `$overrideStore` implement the library's two storage ports; the library itself keeps nothing between
two requests.

## Requirements

PHP 8.5 or later, with `intl` and `mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
