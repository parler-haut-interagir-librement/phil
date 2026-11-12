# Reference

| Type | Kind | Role |
|---|---|---|
| `Almanac` | `final readonly class` | Definitions and a time zone: `between()`, `definitions()` |
| `Definition` | `final readonly class` | A date: key, title, rule, until or days, all day, tags; `spans()` |
| `DateSpan` | `final readonly class` | When: `start`, `end`, `allDay`; `days()`, `overlaps()` |
| `ComputedDate` | `final readonly class` | A definition and one of its spans |
| `Easter` | `final class` | `western()`, `orthodox()` |
| `ChineseNewYear` | `final class` | `of()`, `animal()`, `element()` |
| `Astronomy\Seasons`, `Season` | `final class`, enum | `of()`, `jde()`; the four seasons |
| `Astronomy\MoonPhases`, `MoonPhase`, `Phase` | `final class`, `final readonly class`, enum | `between()`, `jde()`; a phase and its instant; the four phases |
| `Astronomy\Sun` | `final class` | `longitude()`, `declination()`, `rightAscension()`, `equationOfTime()` |
| `Place` | `final readonly class` | `latitude`, `longitude`, `zone` |
| `Astronomy\SunTimes` | `final class` | `on()`: the solar day of a place |
| `Astronomy\SolarDay` | `final readonly class` | `place`, `date`, `noon`, `sun`; `sunrise()`, `sunset()`, `twilight()`, `dawn()`, `dusk()`, `dayLength()` |
| `Astronomy\Crossing`, `Altitude` | `final readonly class`, enum | `morning`, `evening`, `sun`, `seconds()`; `Crosses`, `AlwaysAbove`, `AlwaysBelow` |
| `Astronomy\Twilight` | enum | `Civil`, `Nautical`, `Astronomical`; `depression()` |
| `Astronomy\Eclipses` | `final class` | `solar()`, `lunar()`, `solarAt()`, `lunarAt()` |
| `Astronomy\SolarEclipse`, `SolarEclipseKind` | `final readonly class`, enum | `at`, `kind`, `central`, `gamma`, `magnitude`; `Total`, `Annular`, `Hybrid`, `Partial` |
| `Astronomy\LunarEclipse`, `LunarEclipseKind` | `final readonly class`, enum | `at`, `kind`, `gamma`, `penumbralMagnitude`, `umbralMagnitude`, `penumbral`, `partial`, `total`; `Total`, `Partial`, `Penumbral` |
| `Astronomy\JulianDay` | `final class`, internal | Julian days and ΔT |
| `Rule\Rule` | interface | `in()`, `isInstant()`, `__toString()` |
| `Rule\WithinTheYear` | interface, a `Rule` | Its dates of a year fall at most a week out of it: the almanac reads only the years a period needs |
| `Rule\FixedDate`, `NthWeekday`, `RelativeWeekday`, `EasterSunday`, `DayOfYear`, `WeekdayOnMonthDay`, `MoonPhaseRule`, `SeasonRule`, `ChineseNewYearRule`, `EclipseRule`, `Offset` | `final readonly class` | The rules ([rules.md](rules.md)) |
| `Rule\Relation` | enum | `on or after`, `after`, `on or before`, `before` |
| `Rule\RuleParser` | `final class` | `parse()` |
| `Exception\AlmanacException` | interface | Every exception of the library |
| `Exception\InvalidRule` | `final class`, `InvalidArgumentException` | A rule or a definition that cannot be |
| `Exception\InvalidPlace` | `final class`, `InvalidArgumentException` | A latitude or a longitude out of its range |
| `Exception\OutOfRange` | `final class`, `OutOfRangeException` | A year outside a computation's range |
