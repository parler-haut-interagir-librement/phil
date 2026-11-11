# Testing

| Area | What is covered |
|---|---|
| `OpeningHoursType` and its transformers | Submitting, day names in several languages, time zones, showing, empty hours, unreadable fields |
| `OpeningHoursColumnType` | JSON, nesting, refusals |
| `OpeningHoursExtension`, `Intl`, `PhilOpeningHoursBundle` | Open now with a clock, next openings, the week and special hours in French and English, the configuration |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle in
Toronto: `OpeningHoursBundleTest` stores hours, renders the form and the table template in `fr_CA`. Installed on its
own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/opening-hours-bundle/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `opening_hours_week` over listings of one to two hundred places, against a formatter built per text. From the monorepo root:

```sh
composer bench -- packages/opening-hours-bundle/tests/Benchmark
```
