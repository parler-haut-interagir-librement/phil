# phil/almanac

**Easter, Moon phases, seasons, eclipses, sunrise and sunset, and dates written in words, computed for any year.**

phil/almanac computes dates: Western and Orthodox Easter, the phases of the Moon, the equinoxes and solstices, solar and
lunar eclipses, the Chinese New Year, and the dates people write in words, such as *2nd monday of october*, *easter - 47
days* or *saturday on or after 09-15*. An `Almanac` gathers named definitions and gives the dates of any period,
computed when asked and never stored. It does not know any country's holidays: an application names its own dates. It
also gives the sunrise, the sunset, the solar noon and the civil, nautical and astronomical twilights of a place on a
day.

It is for any PHP application that shows holidays, festivals or sky events, such as a community calendar or a club's
events page. The sky is computed with the algorithms of Jean Meeus, to the minute, for any year from
1000 to 3000, and the Chinese New Year from 1001 to 2999. There are no tables to keep up to date. Reach for
`phil/almanac-bundle` in a Symfony application, where dates come from configuration or the database and show in
Twig. For an event that simply repeats on a fixed pattern, `phil/recurrence` is the closer fit.

It is a plain PHP library that depends on `phil/recurrence` and `ext-mbstring`. Each kind of date is a `Rule` that
writes itself as text, which `RuleParser` reads back. A `Definition` gives a rule a key, a title, a length and tags,
and the almanac turns definitions into `ComputedDate`s for a period.

## Status

| Part | State |
|---|---|
| Equinoxes and solstices, phases of the Moon, the Sun's longitude | Implemented and tested |
| Western and Orthodox Easter | Implemented and tested |
| The Chinese New Year, leap months included; animal and element | Implemented and tested |
| Rules read from text and written back; offsets | Implemented and tested |
| Definitions, spans and the almanac of a period | Implemented and tested |
| Sunrise, sunset, solar noon and the three twilights of a place | Implemented and tested |
| Solar and lunar eclipses: kind, γ, magnitudes, phases | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements. |
| [Rules](doc/rules.md) | Every rule, its text and its dates. |
| [The almanac](doc/almanac.md) | Definitions, how long a date lasts, the dates of a period. |
| [Astronomy and calendars](doc/astronomy.md) | Seasons, Moon, Sun, sunrise and twilights, eclipses, Easter, Chinese New Year: methods, ranges, accuracy. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |
| [Reference](doc/reference.md) | Every type of the library. |

## Quick start

```php
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;

$almanac = new Almanac([
    new Definition('thanksgiving', 'Thanksgiving', RuleParser::parse('2nd monday of october')),
    new Definition('mardi-gras', 'Mardi Gras', RuleParser::parse('easter - 47 days')),
    new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03')),
    new Definition('full-moon', 'Full moon', RuleParser::parse('full moon'), allDay: false),
], new DateTimeZone('America/Toronto'));

foreach ($almanac->between(new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-11-01')) as $date) {
    echo $date->definition->title, ' ', $date->span->start->format('Y-m-d H:i'), "\n";
}
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/recurrence` 0.1, for its weekdays.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
