# Rules

A rule finds dates in a year. `RuleParser::parse()` reads it from its text — English, in any case and spacing —
and its `__toString()` writes the canonical text, which reads back to the same rule: a rule can be kept in a
configuration file or in a column.

| Text | Rule | 2026, in Toronto |
|---|---|---|
| `12-25`, `7-1` | `FixedDate`: the same day every year; February 29 only in leap years | `12-25` |
| `2nd monday of october`, `first saturday of may`, `3rd wednesday of 3` | `NthWeekday`: the 1st to the 5th weekday of a month | `10-12` |
| `last thursday of february` | `NthWeekday`, counted from the end | `02-26` |
| `saturday on or after 09-15`, `tuesday after 09-15` | `RelativeWeekday`: a weekday from a day; `after` never the day itself | `09-19`, `09-22` |
| `monday on or before 05-24`, `monday before 05-25` | `RelativeWeekday`, backwards | `05-18` |
| `easter`, `orthodox easter` | `EasterSunday` | `04-05`, `04-12` |
| `day 256` | `DayOfYear`: day 366 only in leap years | `09-13` |
| `friday 13` | `WeekdayOnMonthDay`: every such day of the year | `02-13`, `03-13`, `11-13` |
| `new moon`, `first quarter`, `full moon`, `last quarter` | `MoonPhaseRule`: every instant of the phase | `full moon`: 13 instants, the first `01-03 05:02` |
| `march equinox`, `june solstice`, `september equinox`, `december solstice` | `SeasonRule`: the instant | `12-21 15:50` |
| `chinese new year` | `ChineseNewYearRule`: China's date | `02-17` |
| `solar eclipse`, `total solar eclipse`, `annular solar eclipse`, `hybrid solar eclipse`, `partial solar eclipse` | `EclipseRule`: the greatest of every solar eclipse, or of one kind | `solar eclipse`: `02-17 07:12`, `08-12 13:46` |
| `lunar eclipse`, `total lunar eclipse`, `partial lunar eclipse`, `penumbral lunar eclipse` | `EclipseRule`, of the Moon | `lunar eclipse`: `03-03 06:34`, `08-28 00:13` |
| any of them, then `+ 3 days` or `- 47 days` | `Offset`: 1 to 366 days either way | `easter - 47 days`: `02-17` |

The ordinals are `1st` to `5th` or `first` to `fifth`, and `last`; a month is its English name or its number. A fifth
weekday a month does not have gives nothing that year. An offset of an offset is refused — `easter + 1 day + 2 days`
is `easter + 3 days` —; a date an offset moves into another year stays its rule's.

A rule of days gives midnights in the time zone asked; a rule of the sky — a phase, a season, an eclipse — gives
instants, `isInstant()`. A year may have no eclipse of a kind: the rule then gives nothing that year. Anything else is
an `InvalidRule`, naming the text, cut at 100 characters.

Every rule of the library implements `WithinTheYear`, a `Rule` whose dates of a year fall in that year or at most a
week out of it: the almanac then reads only the years a period needs. A rule of an application implements it when
its dates keep to that bound, and `Rule` alone otherwise.

```php
RuleParser::parse('Easter - 47 Days');           // easter - 47 days
RuleParser::parse('1st monday of september - 2 days')->in(2026, $zone); // [2026-09-05]
(string) new NthWeekday(-1, Weekday::Thursday, 2); // last thursday of february
```
