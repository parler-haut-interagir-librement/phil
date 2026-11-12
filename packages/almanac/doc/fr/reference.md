<!-- phil-dev source-sha256=5b2d30c628098d97fa737a23f7441513e3a7dfbe1f217ae306f1e5d7fdcb495f -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Almanac` | `final readonly class` | Des définitions et un fuseau horaire : `between()`, `definitions()` |
| `Definition` | `final readonly class` | Une date : clé, titre, règle, fin ou jours, journée entière, étiquettes; `spans()` |
| `DateSpan` | `final readonly class` | Le moment : `start`, `end`, `allDay`; `days()`, `overlaps()` |
| `ComputedDate` | `final readonly class` | Une définition et l'une de ses durées |
| `Easter` | `final class` | `western()`, `orthodox()` |
| `ChineseNewYear` | `final class` | `of()`, `animal()`, `element()` |
| `Astronomy\Seasons`, `Season` | `final class`, enum | `of()`, `jde()`; les quatre saisons |
| `Astronomy\MoonPhases`, `MoonPhase`, `Phase` | `final class`, `final readonly class`, enum | `between()`, `jde()`; une phase et son instant; les quatre phases |
| `Astronomy\Sun` | `final class` | `longitude()`, `declination()`, `rightAscension()`, `equationOfTime()` |
| `Place` | `final readonly class` | `latitude`, `longitude`, `zone` |
| `Astronomy\SunTimes` | `final class` | `on()` : le jour solaire d'un lieu |
| `Astronomy\SolarDay` | `final readonly class` | `place`, `date`, `noon`, `sun`; `sunrise()`, `sunset()`, `twilight()`, `dawn()`, `dusk()`, `dayLength()` |
| `Astronomy\Crossing`, `Altitude` | `final readonly class`, enum | `morning`, `evening`, `sun`, `seconds()`; `Crosses`, `AlwaysAbove`, `AlwaysBelow` |
| `Astronomy\Twilight` | enum | `Civil`, `Nautical`, `Astronomical`; `depression()` |
| `Astronomy\Eclipses` | `final class` | `solar()`, `lunar()`, `solarAt()`, `lunarAt()` |
| `Astronomy\SolarEclipse`, `SolarEclipseKind` | `final readonly class`, enum | `at`, `kind`, `central`, `gamma`, `magnitude`; `Total`, `Annular`, `Hybrid`, `Partial` |
| `Astronomy\LunarEclipse`, `LunarEclipseKind` | `final readonly class`, enum | `at`, `kind`, `gamma`, `penumbralMagnitude`, `umbralMagnitude`, `penumbral`, `partial`, `total`; `Total`, `Partial`, `Penumbral` |
| `Astronomy\JulianDay` | `final class`, interne | Les jours juliens et ΔT |
| `Rule\Rule` | interface | `in()`, `isInstant()`, `__toString()` |
| `Rule\WithinTheYear` | interface, une `Rule` | Ses dates d'une année tombent au plus à une semaine de celle-ci : l'almanach lit seulement les années dont une période a besoin |
| `Rule\FixedDate`, `NthWeekday`, `RelativeWeekday`, `EasterSunday`, `DayOfYear`, `WeekdayOnMonthDay`, `MoonPhaseRule`, `SeasonRule`, `ChineseNewYearRule`, `EclipseRule`, `Offset` | `final readonly class` | Les règles ([rules.md](rules.md)) |
| `Rule\Relation` | enum | `on or after`, `after`, `on or before`, `before` |
| `Rule\RuleParser` | `final class` | `parse()` |
| `Exception\AlmanacException` | interface | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidRule` | `final class`, `InvalidArgumentException` | Une règle ou une définition impossible |
| `Exception\InvalidPlace` | `final class`, `InvalidArgumentException` | Une latitude ou une longitude hors de sa plage |
| `Exception\OutOfRange` | `final class`, `OutOfRangeException` | Une année hors de la plage d'un calcul |
