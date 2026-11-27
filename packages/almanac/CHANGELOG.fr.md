<!-- phil-dev source-sha256=1d23ffb0efe5299255f5abe891c9c303b4ebfdbca6b99e00ac0dd2fa3b6a07ad -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/almanac` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Rule\Rule` et ses sortes — `FixedDate`, `NthWeekday`, `RelativeWeekday`, `EasterSunday`, `DayOfYear`,
  `WeekdayOnMonthDay`, `MoonPhaseRule`, `SeasonRule`, `ChineseNewYearRule`, `EclipseRule`, `Offset` —, lues de leur
  texte par `RuleParser` et récrites par `__toString()`.
- `Definition`, `DateSpan`, `ComputedDate` et `Almanac` : les dates d'une période, par début puis par titre.
  `Rule\WithinTheYear`, implémentée par chaque règle de la bibliothèque : `Almanac::between()` ne lit que les années à
  partir desquelles les dates d'une telle règle peuvent toucher la période, une année pour un mois d'une date fixe là où
  quatre sont lues sans elle.
- `Astronomy\Seasons` et `Season`, `Astronomy\MoonPhases`, `MoonPhase` et `Phase`, `Astronomy\Sun` : les équinoxes et
  les solstices, les phases de la Lune et la longitude du Soleil par les algorithmes de Meeus, de 1000 à 3000 ; le
  Nouvel An chinois de 1001 à 2999.
- `Place`, `Astronomy\SunTimes`, `SolarDay`, `Crossing`, `Altitude` et `Twilight` : le midi solaire, le lever, le
  coucher et les crépuscules civil, nautique et astronomique d'un lieu un jour donné, le soleil de minuit et la nuit
  polaire ; `Sun::declination()`, `rightAscension()` et `equationOfTime()` ; `InvalidPlace`.
- `Astronomy\Eclipses`, `SolarEclipse`, `SolarEclipseKind`, `LunarEclipse` et `LunarEclipseKind` : les éclipses solaires
  et lunaires selon le chapitre 54 de Meeus, leur sorte, γ et leurs magnitudes, et les phases d'une éclipse lunaire ;
  `Rule\EclipseRule`, lue de `solar eclipse`, `total lunar eclipse` et des autres sortes.
- `Easter` : le dimanche de Pâques occidental et orthodoxe, de 1583 à 4099.
- `ChineseNewYear` : le nouvel an selon les règles du calendrier chinois, mois intercalaires compris ; l'animal et
  l'élément de l'année. `ChineseNewYear::of()` calcule une année une fois et la garde pour le processus.
- `AlmanacException`, `InvalidRule`, `InvalidPlace` et `OutOfRange`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
