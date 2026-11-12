# Extension points

The library is plain PHP and declares no service. What an application adds is rules; what it chooses is the
definitions of its almanac and its time zone.

## `Rule`

`Phil\Almanac\Rule\Rule` — a way to find dates in a year. See [Rules](rules.md).

| Method | Contract |
|---|---|
| `in(int $year, DateTimeZone $zone): list<DateTimeImmutable>` | Its dates in that year, in order: midnights in the zone for a rule of days, instants for a rule of the sky |
| `isInstant(): bool` | Whether it finds instants — a phase of the Moon, a solstice — rather than days |
| `__toString(): string` | Its text, for configuration or a column |

`RuleParser` reads the texts of the shipped rules only: a rule of the application's own is built in code, or read by
the application's parser before falling back to `RuleParser::parse()`.

### `WithinTheYear`

`Phil\Almanac\Rule\WithinTheYear` — a marker for a rule whose dates fall in their year, or at most a week out of it. The
almanac then reads only the years a period needs; a rule that does not implement it is read over the two years before
the period and the year after it as well.

## Places

`SunTimes::on()` takes any `Place`: an application keeps its places — a venue's coordinates, a configured city — and
builds them when it asks. The height of the place is not counted; an application that wants it moves the horizon
itself, from `Sun::declination()` and `Sun::equationOfTime()`.

## Almanacs

| Point | How |
|---|---|
| The dates of an almanac | The `Definition`s given to `Almanac`: key, title, rule, an end rule or a number of days, whole days or a time, tags |
| The time zone | The `$zone` given to `Almanac`: every date is computed in it |
| Which dates a page shows | The `$tags` of `Almanac::between()` |
