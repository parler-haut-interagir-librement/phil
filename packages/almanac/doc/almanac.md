# The almanac

## Definitions

A `Definition` is a date the almanac computes:

| Argument | | |
|---|---|---|
| `key` | required | A lowercase letter or digit, then up to 99 of them, `.`, `-` or `_`; one date per key |
| `title` | required | What it is called |
| `rule` | required | When it starts |
| `until` | none | When it ends: the first date of this rule on or after the start, that year or the next |
| `days` | `1` | How many days it lasts, 1 to 366, when there is no `until` |
| `allDay` | `true` | Whole days; `false` only for a rule of the sky, shown at its instant |
| `tags` | `[]` | Words the almanac filters on |

```php
new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03'));
new Definition('engineers-week', 'Engineers week', RuleParser::parse('monday on or before 02-22'), days: 7);
new Definition('full-moon', 'Full moon', RuleParser::parse('full moon'), allDay: false, tags: ['moon']);
```

`spans(year, zone)` gives its `DateSpan`s that start in a year: `start` and `end` — the midnights of the first and
last day, or the same instant —, `allDay`, `days()` and `overlaps(from, until)`. An `until` that never comes in the
year or the next ends the span on its first day.

## The dates of a period

```php
$almanac = new Almanac($definitions, new DateTimeZone('America/Toronto'));
$almanac->between($from, $until);           // every ComputedDate that touches the period
$almanac->between($from, $until, ['moon']); // those with one of the tags
```

- **A period includes its start and not its end**, and its bounds are instants: `new DateTimeImmutable('2026-09-01')`
  in PHP's default time zone is another moment than the almanac's midnight when the two zones differ. Give the bounds
  in the almanac's time zone for its days.
- **The years around the period are read too**: a date an offset moves into it from the year after — `01-06 - 12
  days` — or from two years before, or a span that started then. Where a rule has no date for such a year — Easter
  before 1583 — it is left out; a year of the period itself outside a rule's range is an `OutOfRange`.
- **A rule of the library reads only the years it needs**: its dates fall at most a week out of their year —
  `saturday after 12-31` —, so a period reads the years from which its dates, moved by their offset and lasting their
  days, can touch it; a month of `12-25` reads one year, not four. Such a rule implements `Rule\WithinTheYear`; a rule
  of an application that does not is read over the two years before the period and the one after.
- **A span that started before the period and still runs is in it**: the evening of December 31 brings a date that
  started the day before.
- **The dates come by start, then by title.** Each is a `ComputedDate`: its `definition` and its `span`.

Nothing is stored: computing a year of dates takes well under a millisecond per definition. The Chinese New Year of a
year, which reads more than a year of new moons, is computed once and kept for the process.
