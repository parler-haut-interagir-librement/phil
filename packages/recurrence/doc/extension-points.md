# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through arguments.

| Point | How |
|---|---|
| The time zone of the occurrences | The zone of the start given to `Recurrence::of()`, or the zone given to `RecurrenceRule::parse()` for an `UNTIL` read without one |
| Dates left out | The `$exclusions` of `Recurrence::of()`: cancelled occurrences, holidays the application computes |
| Dates added | The `$additions` of `Recurrence::of()`: a session moved to another day, an extra date |
| Where added and removed dates are kept | `(string) RecurrenceDates::of(...)` writes an `RDATE` or `EXDATE` line, and `RecurrenceDates::parse()` reads it back |
| How many occurrences are read | The `$limit` of `between()`, or `occurrences()` read lazily and stopped by the caller |
| Where a rule is kept | `(string) $rule` writes the RFC 5545 text the application stores, and `RecurrenceRule::parse()` reads it back |
| Rules in words, in other languages | Written by the application over `RecurrenceRule`'s public properties; the library writes no prose |
| Catching the library's errors | Every exception implements `Phil\Recurrence\Exception\RecurrenceException` |

Frequencies finer than a day (`HOURLY`, `MINUTELY`, `SECONDLY`) are refused rather than extended: occurrences at a
time of day come from the start's time.
