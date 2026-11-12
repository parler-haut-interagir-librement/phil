# Testing

| Area | What is covered |
|---|---|
| Seasons, Moon, Sun | Meeus's examples 25.a, 27.a and 49.a; the seasons and phases of 2026 as the United States Naval Observatory publishes them, to the minute; the edges of a search; the ranges; values pinned to a thousandth of a second across the range |
| Sunrise, sunset, twilights | Meeus's examples 25.a and 28.b; sunrise and sunset of Montréal, New York, London, Sydney and Reykjavík at the solstices as published tables give them, to the minute; the twilights of Montréal; the midnight sun, the polar night and a twilight that lasts all night; PHP's `date_sun_info()` within 90 seconds over a year at three latitudes; the ranges |
| Eclipses | Meeus's examples 54.a and 54.b; every solar and lunar eclipse from 2022 to 2026 against NASA's canon — instant, kind, γ, magnitude —; the phases of the lunar eclipse of 2025 September 7; the 224 solar eclipses of the 21st century, 68 of them total; the bounds of a period and the ranges |
| ΔT | Each polynomial at its edges, the long-term parabola |
| Easter | Known dates, and every year from 1583 to 4099 against Gauss's algorithm, Western and Orthodox |
| Chinese New Year | Every year from 1985 to 2050 but a few, 2034 after the leap month of 2033 among them; the leap-month rule; principal terms; animals and elements |
| Rules | Each form read, written back and found in a year, the eclipses' too; edges — fifth weekdays, February 29, a 31st in December, phases on January 1 —; every refusal |
| Definitions and the almanac | Spans, `until` the same day, the next year or never, refusals, periods across years and time zones, tags, order |

```sh
vendor/bin/phpunit packages/almanac/tests
```

Infection holds the library at 100 % of mutants killed; the few mutants that no year of the range can tell apart
are named in the package's `phil-dev.php` with the reason.

## Fuzzing

`RuleParser` is a fuzz target, `rule-parser`: on any text it gives a rule whose text reads back to the same rule, or
an exception of the almanac, never anything else nor a warning. Its seeds are in `tests/Fuzz/corpus/rule-parser`.

```sh
composer fuzz -- rule-parser --budget=60
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/almanac/tests/Benchmark
```
