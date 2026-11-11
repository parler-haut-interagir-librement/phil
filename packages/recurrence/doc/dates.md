# Added and removed dates

`RecurrenceDates::parse()` reads a line of dates as RFC 5545 writes it beside a rule: `RDATE`, the dates added to the
occurrences, or `EXDATE`, those taken away. `(string) $dates` writes it back.

```php
$added = RecurrenceDates::parse('RDATE;TZID=America/Toronto:20261224T190000,20261231T190000');
$removed = RecurrenceDates::parse('EXDATE;VALUE=DATE:20261225', new DateTimeZone('America/Toronto'));

$recurrence = Recurrence::of($start, $rule, exclusions: $removed->dates, additions: $added->dates);

(string) $added;   // "RDATE;TZID=America/Toronto:20261224T190000,20261231T190000"
```

| Form | Read as |
|---|---|
| `RDATE;VALUE=DATE:20261224,20261226` | Whole days, at midnight in the zone of `TZID`, or in the zone given to `parse()` |
| `RDATE:20261224T000000Z` | Times in UTC |
| `RDATE;TZID=America/Toronto:20261224T190000` | Times in a zone PHP knows by its IANA name, or an offset, quoted or not |
| `RDATE:20261224T190000` | Floating times, read in the zone given to `parse()`, or PHP's default zone |

The property name and the parameters are read in any case, and a folded line is unfolded first. A time a change of
time skips — 02:30 on the night clocks go forward — is the time after it, as RFC 5545 says.

| Property | Holds |
|---|---|
| `property` | `RDATE` or `EXDATE` |
| `dates` | The dates, as `DateTimeImmutable`, in the order of the line |
| `isDate` | Whether they are whole days |
| `timezone` | The zone they are written in: the zone of `TZID`, the zone floating times were read in, or `null` for UTC |

`RecurrenceDates::of($property, $dates, $isDate = false, $timezone = null)` builds one: whole days are written as
days, times in their zone with `TZID`, or in UTC without a zone. A zone whose name holds `:`, `;` or `,` — an offset
such as `+05:30` — is quoted. A line read and written back is read again to the same line.

## What is refused

`InvalidRule` names what it refuses: another property than `RDATE` or `EXDATE`; a parameter other than `VALUE` and
`TZID`, or one given twice; periods (`VALUE=PERIOD`), which are not read; a zone PHP does not know, or one with a
character outside letters, digits, `_+-/:.` and spaces, or longer than 64 bytes; a time among days or a day among
times; a date that does not exist, or outside the years 1 to 9999.

**A line costs a bounded time**: at most `RecurrenceDates::MAX_DATES` dates, 1,000, and `RecurrenceDates::MAX_LENGTH`
bytes, 17,500, are read; a longer line is refused before it is read.
