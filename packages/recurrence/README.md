# phil/recurrence

**Repeating dates from an RFC 5545 rule: read it, check it, write it, list its occurrences.**

phil/recurrence reads the `RRULE`s of iCalendar: yearly, monthly, weekly and daily, every so many periods, by weekday
with its place in the month or year, by month day from the start or the end, by month and by set position, until a
date or a number of times. It checks a rule against the RFC, writes it back as text, and expands it into
occurrences, with the dates `RDATE` adds and without those its `EXDATE` exceptions take away, and reads and writes
their lines. It can also describe a recurrence as a schema.org `Schedule`. It does not read hourly or finer
frequencies, week numbers or year days, nor periods of `RDATE`.

It is for any PHP application whose events repeat: a weekly meeting, a monthly club night, a yearly festival. Rules
follow the RFC so that they travel to and from other calendars unchanged. The library follows
RFC 5545 and depends on nothing but PHP and `ext-mbstring`. It is not a calendar: `phil/calendar-bundle` is one,
built on it. In a Symfony application, `phil/recurrence-bundle` adds a form, a Doctrine column type and Twig
functions around it.

The design rests on two value types. `RecurrenceRule` holds a rule and refuses one the RFC does not allow.
`Recurrence` pairs a rule with a first occurrence and gives the occurrences in that occurrence's time zone, keeping
its wall-clock time across changes of time.

## Status

| Part | State |
|---|---|
| `RecurrenceRule`: reading, checking against RFC 5545, writing | Implemented and tested |
| `Recurrence`: occurrences, between moments, next one, exceptions | Implemented and tested |
| `RDATE` merged into the occurrences outside `COUNT`; `RecurrenceDates`: `RDATE` and `EXDATE` lines read and written | Implemented and tested |
| The examples of RFC 5545, section 3.8.5.3, that the library reads | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Rules](doc/rules.md) | `RecurrenceRule`, its parts, what is checked, what is not read. |
| [Occurrences](doc/occurrences.md) | `Recurrence`, time zones, ends, exceptions, added dates. |
| [Added and removed dates](doc/dates.md) | `RecurrenceDates`, the `RDATE` and `EXDATE` lines read and written. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/recurrence
```

```php
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;

$rule = RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU;COUNT=6');
$meeting = Recurrence::of(new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')), $rule);

$meeting->between(limit: 3);                  // 2026-11-10, 2026-12-08, 2027-01-12, at 19:00 in Toronto
$meeting->next(new DateTimeImmutable());      // the next one, or null
```

## Requirements

PHP 8.5 or later with `ext-mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
