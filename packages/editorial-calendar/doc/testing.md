# Testing

## The package's tests

`tests/Unit` covers the items, their checks and their workflow, authors by identifier, batches, moments in UTC and their
order; the calendar, its identifiers, its batches applied whole or refused whole, due and manual batches; the views,
their days in a time zone across a change of time, their weeks from each first day, their entries and their order,
transitions read through the port; the review and its rules at their bounds; and the iCalendar stream, its escaping and
its folding.

From the monorepo root:

```sh
vendor/bin/phpunit packages/editorial-calendar/tests
```

In the monorepo, Infection holds the package at 100 % of covered mutants.

## The benchmarks

`tests/Benchmark/CalendarViewBench.php` shows a month, reviews it and exports a year as iCalendar, over a calendar of
5,000 items, a fifth of them in 500 batches. Each costs a time that grows with the items, never with the items times
the batches.

```sh
vendor/bin/phil-dev bench -- --filter=CalendarViewBench packages/editorial-calendar/tests/Benchmark
```
