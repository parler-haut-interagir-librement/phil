# Occurrences

```php
$recurrence = Recurrence::of($start, $rule, exclusions: [$cancelled], additions: [$extra]);
```

| Method | Gives |
|---|---|
| `occurrences()` | Every occurrence, in order, as a generator |
| `between($from, $through, $limit = 1000)` | Those that start from one moment through another, at most so many |
| `next($after)` | The first that starts after a moment, or `null` when the rule has ended |

**The first occurrence is always one**, and counts in `COUNT`; occurrences keep its time of day on the wall clock of
its time zone, so that a weekly meeting at 19:00 in Toronto stays at 19:00 across changes of time, and a time a
change skips — 02:30 on the night clocks go forward — is the time after it, as RFC 5545 says.

**Ends**: an end time is the last moment an occurrence may start; an end day keeps all its occurrences, in the first
occurrence's time zone.

**Exceptions**, `EXDATE`, are taken away from the occurrences, and count in `COUNT` as RFC 5545 says.

**Added dates**, `RDATE`, are merged into the occurrences in order, in the first occurrence's time zone, at the
instant they name. A date the rule gives too comes once. They count in neither `COUNT` nor `UNTIL`: a rule of three
occurrences with two added dates gives five, and an added date after the rule's end is still one. An exception takes an
added date away as it does any occurrence. `between()` and `next()` find them in their window; the added dates after
the rule's last occurrence follow it. [Added and removed dates](dates.md) reads and writes their lines.

**Days that do not exist** are skipped: the 31st of a month of 30 days, February 29 of a common year. A rule that
matches no day at all — the 30th of February — stops after `Recurrence::MAX_PERIODS` periods in a row without a day;
a rule that keeps matching days goes on until its own end, or the end of the year 9999, the last a day is written in
with four digits.

**Windows cost what they hold**: without `COUNT`, `between()` and `next()` start at the period the moment falls in,
reached by arithmetic from the first occurrence, and `between()` stops at the period of its last moment. The cost of a
month of a rule is the same in its first year and in its fiftieth. A rule with `COUNT` is walked from its first
occurrence, since the occurrences before the window count: which occurrence is the last depends on every period
before it. A period is laid out with its days as numbers, and a date is built only for a day the rule keeps: walking a
year of a yearly rule, or a long count, costs the occurrences it holds, not a date for each day of the year.

**A call costs at most `Recurrence::MAX_WALKED` periods**, 50,000, whatever it finds: `limit` caps the occurrences
`between()` returns, and this caps the work of `between()` and `next()`. A rule that matches one day in years — the
29th of February of a daily rule — gives the occurrences those periods hold, and `next()` gives `null` past them; a
rule with `COUNT`, walked from its first occurrence, gives up after as many periods. A rule taken from an import or a
request thus costs a bounded time. `occurrences()` walks as far as its caller asks.
