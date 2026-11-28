# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network:

- the reader on calendars written by hand after RFC 5545, under `tests/Fixtures` and inline: zones by IANA name,
  Windows name and `VTIMEZONE`, whole days, floating times, recurrences with exceptions and changed occurrences,
  cancellations, duplicate UIDs, every refusal and every bound;
- property tests with a fixed seed on unfolding: a text folded at random places reads as the text unfolded;
- the feed through Symfony's `MockHttpClient`: `200`, `304`, `404`, `500`, an answer too large, a redirect, a private
  network;
- snapshots, differences, failures, the refresh policy and overrides through `CalendarImporter`, with the in-memory
  stores and source of `tests/Double`, which an application's tests may copy.

In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

The fuzz target `ics-reader`, `tests/Fuzz/ics-reader.php`, reads texts nobody wrote: whatever the text, the reader
gives events that each have a UID, end at or after they start, appear once per key and never pass the bound, or
throws `UnreadableCalendar`; never another exception, never a warning. In the monorepo:

```sh
composer fuzz -- ics-reader
```

Its seeds are in `tests/Fuzz/corpus/ics-reader`.

## Benchmark

`tests/Benchmark/ICalendarReaderBench.php` reads a feed of 2,000 events — in a named zone, with folded descriptions,
categories, and one event in ten repeating with an exception —, as each scheduled refresh does: the time must stay in
proportion to the size of the feed.
