# phil/opening-hours

**A place's opening hours, and whether it is open right now.**

phil/opening-hours holds a place's hours as value objects. A week has its hours, with ranges that may close past
midnight. Special hours replace them for a day or a period, such as a holiday when the place is closed or a summer
with other hours. The library says whether the place is open at a moment and when it next opens, in the place's time
zone. It groups the week in runs of days with the same hours, reads and writes a text form people type, gives
schema.org's `openingHoursSpecification`, and turns hours into an array of texts for one JSON column. It does not
name days or write times in a language, which `phil/opening-hours-bundle` does with ICU. It holds no public holidays
either: special hours are the place's own.

It is for any application that lists places people visit: a shop, a library, a club, a venue. A visitor wants to
know whether the door is open now, and a search engine wants the hours in a form it reads.

It is a plain PHP library, usable in any PHP code, with no dependency beyond `ext-mbstring`. In a Symfony
application, reach for `phil/opening-hours-bundle`, which adds a form, a Doctrine column type and Twig functions.

The design rests on small immutable types: `TimeOfDay`, `TimeRange`, `DayHours`, `SpecialHours` and `OpeningHours`.
A moment is turned to the place's time zone before it is read, so asking at 14:30 UTC in January about a shop in
Toronto asks about 09:30 there.

## Status

| Part | State |
|---|---|
| `TimeOfDay`, `TimeRange`, `DayHours`, `SpecialHours`, `OpeningHours` | Implemented and tested |
| `isOpenAt()`, `nextOpening()`, `groups()` | Implemented and tested |
| `HoursText`, `OpeningHoursSchema`, `OpeningHoursArray` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Hours](doc/hours.md) | Times, ranges, days, special hours, and the time zone. |
| [Asking](doc/asking.md) | Open now, next opening, the hours of a day, the week in runs. |
| [Text, schema.org and storage](doc/text-schema-storage.md) | The text form, the schema.org specifications, the array. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/opening-hours
```

```php
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;

$weekday = HoursText::day('09:00-12:00, 13:00-17:00');
$hours = OpeningHours::of(
    [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => HoursText::day('09:00-17:00, 20:00-02:00')],
    HoursText::specials("2026-12-25 # Christmas"),
    'America/Toronto',
);

$hours->isOpenAt(new DateTimeImmutable());   // true or false
$hours->nextOpening(new DateTimeImmutable()); // the next opening, in Toronto
```

## Requirements

PHP 8.5 or later with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
