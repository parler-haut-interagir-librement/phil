# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

| Area | What is covered |
|---|---|
| Values | Busy periods in UTC, overlaps and cuts; windows and their bounds; periods joined, tentative or firm, and their most; connections, credentials and events refused at each bound; credentials never serialised nor dumped; the messages of the failures |
| `CalendarSync` and the messages | Busy times read, joined and stored; a failed read that leaves the store alone; events written and removed; unknown connections and adapters; the window of a message; an event message that writes or removes |
| The iCalendar reader | Times in UTC, in their zone, in a Windows zone, in the calendar's zone; whole days; cancelled, transparent and tentative events; rules across a change of time with added, removed and changed occurrences; free/busy times; folding, line ends, alarms and a byte order mark; each refusal and each bound, at its most and one past it |
| The iCalendar writer | A timed event in UTC, a whole day read back, texts escaped, lines folded between characters |
| The adapters | Each request — method, address, headers, body —, each answer read, each error status, refused credentials, answers that cannot be read, pages bounded, a redirect not followed, a private network refused, an answer too large, a transport error that hides the address |

The adapters are tested against Symfony's `MockHttpClient`, with fixed addresses: no test resolves a name or opens a
connection.

## Benchmark

`tests/Benchmark/ICalendarReaderBench.php` reads feeds of 100, 1,000 and 5,000 events — a tenth of them weekly rules
with a date taken away, each with a description skipped — and joins their periods over 60 days:

```sh
composer bench -- packages/calendar-sync/tests/Benchmark
```

## Fuzzing

The target `calendar-sync-icalendar` gives the iCalendar reader texts nobody wrote, from the seeds of
`tests/Fuzz/corpus/calendar-sync-icalendar`. Each gives periods inside the window, each ending after it starts, at most
10,000 of them, or `InvalidCalendarData`, never another exception.

```sh
composer fuzz -- calendar-sync-icalendar
```
