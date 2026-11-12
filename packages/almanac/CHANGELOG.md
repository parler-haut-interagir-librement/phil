# Changelog

All notable changes to `phil/almanac` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Rule\Rule` and its kinds — `FixedDate`, `NthWeekday`, `RelativeWeekday`, `EasterSunday`, `DayOfYear`,
  `WeekdayOnMonthDay`, `MoonPhaseRule`, `SeasonRule`, `ChineseNewYearRule`, `EclipseRule`, `Offset` —, read from their
  text by `RuleParser` and written back by `__toString()`.
- `Definition`, `DateSpan`, `ComputedDate` and `Almanac`: the dates of a period, by start then title.
  `Rule\WithinTheYear`, implemented by every rule of the library: `Almanac::between()` reads only the years from
  which such a rule's dates can touch the period, one year for a month of a fixed date where four are read without it.
- `Astronomy\Seasons` and `Season`, `Astronomy\MoonPhases`, `MoonPhase` and `Phase`, `Astronomy\Sun`: equinoxes and
  solstices, the phases of the Moon and the Sun's longitude by the algorithms of Meeus, from 1000 to 3000; the
  Chinese New Year from 1001 to 2999.
- `Place`, `Astronomy\SunTimes`, `SolarDay`, `Crossing`, `Altitude` and `Twilight`: the solar noon, sunrise, sunset
  and the civil, nautical and astronomical twilights of a place on a day, the midnight sun and the polar night;
  `Sun::declination()`, `rightAscension()` and `equationOfTime()`; `InvalidPlace`.
- `Astronomy\Eclipses`, `SolarEclipse`, `SolarEclipseKind`, `LunarEclipse` and `LunarEclipseKind`: solar and lunar
  eclipses by chapter 54 of Meeus, their kind, γ and magnitudes, and the phases of a lunar eclipse;
  `Rule\EclipseRule`, read from `solar eclipse`, `total lunar eclipse` and the other kinds.
- `Easter`: Western and Orthodox Easter Sunday, 1583 to 4099.
- `ChineseNewYear`: the new year by the rules of China's calendar, leap months included; the year's animal and
  element. `ChineseNewYear::of()` computes a year once and keeps it for the process.
- `AlmanacException`, `InvalidRule`, `InvalidPlace` and `OutOfRange`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
