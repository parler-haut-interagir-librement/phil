# Rules

`RecurrenceRule::parse()` reads a rule as RFC 5545 writes it, with or without `RRULE:`, its parts in any order and
case; `(string) $rule` writes it back, its parts in a fixed order, those with their default left out.

```php
$rule = RecurrenceRule::parse('rrule:freq=weekly;interval=2;byday=mo,we;until=20261231T235959Z');
(string) $rule;   // "FREQ=WEEKLY;INTERVAL=2;UNTIL=20261231T235959Z;BYDAY=MO,WE"
```

| Part | Holds |
|---|---|
| `FREQ` | `YEARLY`, `MONTHLY`, `WEEKLY` or `DAILY` |
| `INTERVAL` | Every so many periods, 1 by default |
| `COUNT`, `UNTIL` | So many occurrences, or the last moment one may start: a day (`20261231`), a UTC time (`…T235959Z`), or a floating time read in the time zone given to `parse()` |
| `BYDAY` | Weekdays, with their place in the month or the year in monthly and yearly rules: `MO`, `2TU`, `-1FR` |
| `BYMONTHDAY` | Days of the month, from the end when negative: `1`, `15`, `-1` |
| `BYMONTH` | Months, 1 to 12 |
| `BYSETPOS` | The places, in each period, of the days the other parts keep: `-1`, the last |
| `WKST` | The day weeks start on, Monday by default |

`RecurrenceRule::of()` builds one from its parts.

## What is checked

As RFC 5545 asks, a rule is refused with `InvalidRule` for: an interval or a count under 1; both a count and an end;
an end day without its end; a place in the month or year in a weekly or daily rule, beyond 5 in a monthly one, beyond
53 or 0 anywhere; month days in a weekly rule, beyond 31 or 0; months outside 1 to 12; set positions beyond 366 or 0,
or without another `BY` part. Hourly and finer frequencies, `BYHOUR`, `BYMINUTE`, `BYSECOND`, `BYWEEKNO` and
`BYYEARDAY` are not read: `InvalidRule` names them.

`of()` and `parse()` refuse the same rules, so that every rule written as text is read back: an interval or a count
above `RecurrenceRule::MAX_NUMBER`, 9999, the four digits `parse()` reads; an end outside the years 1 to 9999, in UTC
for an end time and as it is for an end day, since `UNTIL` writes its year with four digits. A rule a form or an
import builds can thus be stored and hydrated again.
