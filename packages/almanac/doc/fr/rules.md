<!-- phil-dev source-sha256=5395c75d8c1699e64af9acb9ce69c65eaafb2a186c929931c0afa1de39239b7c -->
# Règles

Une règle trouve des dates dans une année. `RuleParser::parse()` la lit à partir de son texte, en anglais, quelles que
soient la casse et les espaces. Son `__toString()` écrit le texte canonique, qui se relit en la même règle : une règle
peut donc être gardée dans un fichier de configuration ou dans une colonne.

| Texte | Règle | 2026, à Toronto |
|---|---|---|
| `12-25`, `7-1` | `FixedDate` : le même jour chaque année; le 29 février seulement les années bissextiles | `12-25` |
| `2nd monday of october`, `first saturday of may`, `3rd wednesday of 3` | `NthWeekday` : du 1er au 5e jour de la semaine donné d'un mois | `10-12` |
| `last thursday of february` | `NthWeekday`, compté à partir de la fin | `02-26` |
| `saturday on or after 09-15`, `tuesday after 09-15` | `RelativeWeekday` : un jour de la semaine à partir d'un jour; `after` exclut toujours le jour lui-même | `09-19`, `09-22` |
| `monday on or before 05-24`, `monday before 05-25` | `RelativeWeekday`, vers l'arrière | `05-18` |
| `easter`, `orthodox easter` | `EasterSunday` | `04-05`, `04-12` |
| `day 256` | `DayOfYear` : le jour 366 seulement les années bissextiles | `09-13` |
| `friday 13` | `WeekdayOnMonthDay` : chaque jour de ce genre dans l'année | `02-13`, `03-13`, `11-13` |
| `new moon`, `first quarter`, `full moon`, `last quarter` | `MoonPhaseRule` : chaque instant de la phase | `full moon` : 13 instants, le premier `01-03 05:02` |
| `march equinox`, `june solstice`, `september equinox`, `december solstice` | `SeasonRule` : l'instant | `12-21 15:50` |
| `chinese new year` | `ChineseNewYearRule` : la date de la Chine | `02-17` |
| `solar eclipse`, `total solar eclipse`, `annular solar eclipse`, `hybrid solar eclipse`, `partial solar eclipse` | `EclipseRule` : le maximum de chaque éclipse solaire, ou d'un seul genre | `solar eclipse` : `02-17 07:12`, `08-12 13:46` |
| `lunar eclipse`, `total lunar eclipse`, `partial lunar eclipse`, `penumbral lunar eclipse` | `EclipseRule`, de la Lune | `lunar eclipse` : `03-03 06:34`, `08-28 00:13` |
| n'importe laquelle, suivie de `+ 3 days` ou de `- 47 days` | `Offset` : de 1 à 366 jours dans un sens ou dans l'autre | `easter - 47 days` : `02-17` |

Les ordinaux sont `1st` à `5th` ou `first` à `fifth`, et `last`. Un mois s'écrit par son nom anglais ou par son
numéro. Un cinquième jour de la semaine qu'un mois n'a pas ne donne rien cette année-là. Un décalage d'un décalage est
refusé : `easter + 1 day + 2 days` s'écrit `easter + 3 days`. Une date qu'un décalage fait passer dans une autre année
reste celle de sa règle.

Une règle de jours donne des minuits dans le fuseau horaire demandé. Une règle du ciel (une phase, une saison, une éclipse) donne
des instants, `isInstant()`. Une année peut n'avoir aucune éclipse d'un genre : la règle ne donne alors rien cette
année-là. Tout autre texte produit une `InvalidRule`, qui nomme le texte, coupé à 100 caractères.

Chaque règle de la bibliothèque implémente `WithinTheYear`, une `Rule` dont les dates d'une année tombent dans cette
année ou au plus à une semaine de celle-ci : l'almanach lit alors seulement les années dont une période a besoin. Une
règle d'application l'implémente quand ses dates respectent cette limite, et implémente `Rule` seule autrement.

```php
RuleParser::parse('Easter - 47 Days');           // easter - 47 days
RuleParser::parse('1st monday of september - 2 days')->in(2026, $zone); // [2026-09-05]
(string) new NthWeekday(-1, Weekday::Thursday, 2); // last thursday of february
```
