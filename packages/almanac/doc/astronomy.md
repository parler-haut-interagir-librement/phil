# Astronomy and calendars

The sky is computed, never read from a table, by the algorithms of Jean Meeus, *Astronomical Algorithms*, 2nd edition
(Willmann-Bell, 1998). Every instant is in UTC.

| Type | Method | Range | Accuracy |
|---|---|---|---|
| `Seasons::of(year, Season)` | Chapter 27: the mean instant of table 27.B, the 24 periodic terms of table 27.C | 1000–3000 | Within a minute over the 21st century |
| `MoonPhases::between(from, until, phases)`, `jde(k, phase)` | Chapter 49: the mean phase, its periodic terms and the 14 planetary arguments | 1000–3000 | A few seconds |
| `Sun::longitude(jde)`, `declination(jde)`, `rightAscension(jde)` | Chapter 25, low accuracy | any | 0.01° |
| `Sun::equationOfTime(jde)` | Chapter 28, Smart's formula 28.3 | any | A few seconds |
| `SunTimes::on(place, day)` | The transit and the hour angle of each altitude, read again four times | 1000–3000 | A minute between the polar circles |
| `Eclipses::solar(from, until)`, `lunar(from, until)`, `solarAt(k)`, `lunarAt(k)` | Chapter 54 | 1000–3000 | A few minutes; γ within 0.002 |

The algorithms give Terrestrial Time; `JulianDay` takes ΔT off with the polynomials of Espenak and Meeus from 1900 to
2150 — 75 s for mid-2026, where 69 s are observed — and their long-term parabola outside: about 35 minutes in 1000
and 74 in 3000. ΔT is not known that far from today: an instant there may be some minutes off, still far less than a
day.

## Sunrise, sunset and twilights

A `Place` is a latitude, north positive, a longitude, east positive, in decimal degrees, and the time zone its times
are read in; a coordinate out of its range is an `InvalidPlace`. `SunTimes::on($place, $day)` gives the `SolarDay` of
the date `$day` falls on in that time zone:

| Member | Gives |
|---|---|
| `noon` | The solar noon, the Sun's transit |
| `sunrise()`, `sunset()` | When the Sun's upper limb crosses a sea horizon; null under the midnight sun or in the polar night |
| `dawn(Twilight)`, `dusk(Twilight)` | When a twilight begins and ends — civil by default, nautical, astronomical: the Sun's centre 6°, 12° or 18° below the horizon |
| `sun`, `twilight(Twilight)` | The `Crossing` of each altitude: `morning`, `evening`, and `Altitude` — `Crosses`, `AlwaysAbove`, `AlwaysBelow` |
| `dayLength()` | The seconds from sunrise to sunset: 86 400 under the midnight sun, 0 in the polar night |

The transit is mean noon at the place's longitude, corrected by the equation of time. Each crossing starts from it:
the hour angle at which the Sun's centre reaches the altitude, for the Sun's declination there, gives a first instant,
where the declination and the equation of time are read again, four times. Sunrise and sunset take the Sun's
centre 0.8333° below the horizon — 34′ of refraction and 16′ of the Sun's half-diameter —, as published tables do;
the place's height and the terrain around it are not counted.

A crossing belongs to the solar day around the date's noon: in Reykjavík in June, the sunset after midnight is the
evening's, on the next date. The times are within a minute of the published ones between the polar circles; near the
days where the Sun only grazes an altitude, a few minutes.

```php
use Phil\Almanac\Astronomy\SunTimes;
use Phil\Almanac\Astronomy\Twilight;
use Phil\Almanac\Place;

$zone = new DateTimeZone('America/Toronto');
$day = SunTimes::on(new Place(45.5017, -73.5673, $zone), new DateTimeImmutable('2026-06-20', $zone));

$day->sunrise()?->format('H:i');               // 05:05
$day->sunset()?->format('H:i');                // 20:46
$day->dawn(Twilight::Nautical)?->format('H:i'); // 03:36
```

## Eclipses

`Eclipses::solar($from, $until)` and `Eclipses::lunar($from, $until)` give the eclipses whose greatest falls in a
period, its end excluded, in order. Each new or full moon near a node of the Moon's orbit is corrected to the instant
of the greatest eclipse; its `gamma`, the least distance of the shadow's axis from the Earth's centre in Earth radii,
and the radius `u` of the umbra tell whether there is an eclipse and of which kind.

| Type | Members |
|---|---|
| `SolarEclipse` | `at`; `kind`, a `SolarEclipseKind` — `Total`, `Annular`, `Hybrid`, `Partial` —; `central`; `gamma`; `magnitude`, for a partial eclipse only |
| `LunarEclipse` | `at`; `kind`, a `LunarEclipseKind` — `Total`, `Partial`, `Penumbral` —; `gamma`; `penumbralMagnitude`, `umbralMagnitude`; the spans `penumbral`, `partial` and `total`, each a start and an end, null for a phase the eclipse does not have |

The method finds every eclipse — 224 solar eclipses in the 21st century, 68 of them total —, gives the greatest within
a few minutes and the magnitudes within a few hundredths. It does not give the path of a solar eclipse on the Earth,
nor where an eclipse can be seen: a lunar eclipse is seen from wherever the Moon is up, a solar eclipse only from part
of the Earth.

## Easter

`Easter::western()` follows the Gregorian computus (the anonymous algorithm of Meeus's chapter 8);
`Easter::orthodox()` the Julian computus, written in the Gregorian calendar — 13 days later from 1900 to 2099. Both
cover 1583 to 4099 and give midnight in the time zone asked.

## The Chinese New Year

`ChineseNewYear::of()` follows the rules of China's calendar since 1645: days in China's time; the eleventh month
holds the December solstice; a year of thirteen months, from one eleventh month to the next, makes its first month
without a principal term — the Sun entering a sign — a leap month. The new year is two new moons after the eleventh
month's start, three when a leap eleventh or twelfth month falls between, as at the end of 2033.
`animal()` and `element()` follow the cycles of twelve and of ten: 2026 is the year of the fire horse. A year reads
the December solstice before it and the new moons after the next one: `of()` gives the years from 1001 to 2999,
`ChineseNewYear::FIRST_YEAR` to `LAST_YEAR`.

The range errors are `OutOfRange`.
