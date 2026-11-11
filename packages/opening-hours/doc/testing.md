# Testing

| Area | What is covered |
|---|---|
| Times, ranges, days | Every written form, refusals, ranges past midnight, overlaps |
| `OpeningHours`, `SpecialHours` | Open or not at boundaries and in other time zones, the night before, special hours, next openings, runs |
| `HoursText`, `OpeningHoursSchema`, `OpeningHoursArray` | Lines, labels, specifications, the round trip and each refusal |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/opening-hours/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `nextOpening()` for open and always-closed places with up to a hundred special periods. From the monorepo root:

```sh
composer bench -- packages/opening-hours/tests/Benchmark
```
