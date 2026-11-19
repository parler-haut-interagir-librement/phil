# phil/editorial-calendar

**An editorial calendar of planned publications, with batches published whole or not at all.**

phil/editorial-calendar plans what a site publishes and when. Each planned item names the content it publishes by
reference (`article:42`), its language, its section, and its author by a stable identifier — never an email address —,
and moves through a workflow: idea, drafting, ready, scheduled, published, withdrawn. Moments are kept in UTC; views
cut them into days in the reader's time zone.

Items can be grouped into publication batches — a launch, a series, a translated set — shown as one entry and applied
as a whole: at their moment or by hand, every item of a batch is published, or none is and the calendar stays as it
was. The calendar describes that contract; the application runs it in one transaction with its scheduler.

The calendar shows a day, a week or a month in a time zone, cut into its days and correct across changes of time, and
a list between two moments, with the transitions a scheduler plans elsewhere read through a port, filtered by author,
section and state. Templates draw a view with Twig and the recipes of the PHIL UX kit, without JavaScript. A review
reports the same content planned twice in one language, items planned while still ideas or drafts, publications closer
than a chosen spacing, and stretches without any. An iCalendar writer exports the publications for a calendar
application to subscribe to.

It is a plain PHP library: immutable values and stateless services, with no dependency beyond `ext-mbstring` and
`phil/resource`.

## Status

| Part | State |
|---|---|
| Planned items, authors by identifier, the workflow of states | Implemented and tested |
| Publication batches, applied whole or not at all, due and manual batches | Implemented and tested |
| Day, week, month and list views in a time zone, transitions read through a port | Implemented, tested and benchmarked |
| Filters by author, section and state | Implemented and tested |
| The review: repeated content, unready items, spacing, gaps | Implemented, tested and benchmarked |
| The iCalendar export (RFC 5545) | Implemented, tested and benchmarked |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Planning](doc/planning.md) | Items, authors, states and the workflow, moments in UTC, the calendar. |
| [Publication batches](doc/batches.md) | Batches, their moment, applying them whole, the contract with a scheduler. |
| [Views](doc/views.md) | Days, weeks, months and lists, entries, time zones, filters, transitions planned elsewhere. |
| [Twig](doc/twig.md) | Drawing a view with Twig and the recipes of the PHIL UX kit. |
| [Review](doc/review.md) | The conflicts and gaps reported, and their rules. |
| [iCalendar](doc/icalendar.md) | The export for calendar applications. |
| [Extension points](doc/extension-points.md) | What an application implements, configures or relies on. |
| [Testing](doc/testing.md) | The tests and the benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/editorial-calendar
```

```php
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\View\CalendarView;
use Phil\Resource\ResourceReference;

$author = new AuthorId('0192f3c4-7d1e-7b2a-9c3d-4e5f6a7b8c9d');
$calendar = EditorialCalendar::of(
    [
        PlannedItem::plan('launch-fr', ResourceReference::of('article', '42'), 'Lancement', 'fr', $author),
        PlannedItem::plan('launch-en', ResourceReference::of('article', '43'), 'Launch', 'en', $author),
    ],
    [new PublicationBatch('launch', 'Launch', ['launch-fr', 'launch-en'], new DateTimeImmutable('2026-12-01 09:00', new DateTimeZone('America/Toronto')))],
);

$calendar = $calendar->transition('launch-fr', ItemState::Drafting); // and so on, to ready

$now = new DateTimeImmutable();
$week = new CalendarView()->week($calendar, new DateTimeImmutable(), new DateTimeZone('America/Toronto'));

foreach ($calendar->dueBatches($now) as $batch) {
    $calendar = $calendar->applyBatch($batch->id, $now); // every item published, or BatchFailed and nothing changed
}
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, and `phil/resource`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
