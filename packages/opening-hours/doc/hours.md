# Hours

| Type | Is | Read from |
|---|---|---|
| `TimeOfDay` | A time to the minute, `00:00` to `24:00` | `9`, `9h`, `9h30`, `09:30`, `24:00` |
| `TimeRange` | When a place opens and closes | `09:00-17:00`, `9h–17h`, `10 to 24` |
| `DayHours` | A day's ranges, in order and apart; none is closed | `09:00-12:00, 13:00-17:00` |
| `SpecialHours` | Hours that replace the week's from a day through another, with a label | see [the text form](text-schema-storage.md) |
| `OpeningHours` | The week, the special hours, the time zone | |

**A range that closes before it opens closes the next day**: `18:00-02:00` is a Friday evening that ends Saturday at
2. Only the last range of a day may do so; ranges that overlap are refused, ranges that touch are not. `24:00`
closes at the end of the day; no range opens at it.

**Special hours** cover calendar days, `Y-m-d`, from one through another; without hours they close the place. The
first special hours that cover a day win, so a day inside a period is given before it.

**The time zone** is an IANA identifier, `UTC` by default: the hours are the place's wall clock, whatever the time
zone of the moment asked about.

```php
OpeningHours::of(
    week: [1 => HoursText::day('09:00-17:00'), 6 => HoursText::day('10:00-16:00')],   // ISO day numbers; missing days are closed
    specials: [SpecialHours::of('2026-12-24', hours: HoursText::day('09:00-12:00'), label: 'Christmas Eve'), SpecialHours::of('2026-12-24', '2027-01-02')],
    timezone: 'America/Toronto',
);
```

`InvalidOpeningHours` is thrown for a time, a range, a period, a label longer than 100 characters, or a time zone that
cannot be.
