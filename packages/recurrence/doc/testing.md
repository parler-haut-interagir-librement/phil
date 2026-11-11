# Testing

| Area | What is covered |
|---|---|
| `RecurrenceRule`, `WeekdayNum`, `Weekday` | Reading, writing, every refusal and every limit of RFC 5545 |
| `Recurrence` | The examples of RFC 5545 the library reads, changes of time, ends, exceptions, added dates, limits, rules matching nothing |
| `RecurrenceDates` | Every form of `RDATE` and `EXDATE` read and written back, every refusal and every limit |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/recurrence/tests
```

## Fuzzing

The fuzz target `recurrence-dates` gives the reader of `RDATE` and `EXDATE` lines inputs nobody wrote, from the seeds
of `tests/Fuzz/corpus/`: whatever it reads, it gives a line that is read again the same once written back, or refuses
the line with one of its own exceptions, and never throws anything else nor warns. From the monorepo root:

```sh
composer fuzz -- recurrence-dates
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/recurrence/tests/Benchmark
```
