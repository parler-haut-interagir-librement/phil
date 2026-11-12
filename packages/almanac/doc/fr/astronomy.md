<!-- phil-dev source-sha256=eaa7f03e81bcad8232adddd5d5f6fca451601c2890d5b21025bdfcb8a76b967f -->
# Astronomie et calendriers

Le ciel est calculé, jamais lu dans une table, avec les algorithmes de Jean Meeus, *Astronomical Algorithms*,
2e édition (Willmann-Bell, 1998). Chaque instant est en UTC.

| Type | Méthode | Plage | Précision |
|---|---|---|---|
| `Seasons::of(year, Season)` | Chapitre 27 : l'instant moyen du tableau 27.B, les 24 termes périodiques du tableau 27.C | 1000–3000 | À une minute près sur le XXIe siècle |
| `MoonPhases::between(from, until, phases)`, `jde(k, phase)` | Chapitre 49 : la phase moyenne, ses termes périodiques et les 14 arguments planétaires | 1000–3000 | Quelques secondes |
| `Sun::longitude(jde)`, `declination(jde)`, `rightAscension(jde)` | Chapitre 25, faible précision | toutes | 0,01° |
| `Sun::equationOfTime(jde)` | Chapitre 28, formule 28.3 de Smart | toutes | Quelques secondes |
| `SunTimes::on(place, day)` | Le passage au méridien et l'angle horaire de chaque hauteur, relus quatre fois | 1000–3000 | Une minute entre les cercles polaires |
| `Eclipses::solar(from, until)`, `lunar(from, until)`, `solarAt(k)`, `lunarAt(k)` | Chapitre 54 | 1000–3000 | Quelques minutes; γ à 0,002 près |

Les algorithmes donnent le Temps terrestre. `JulianDay` retire ΔT avec les polynômes d'Espenak et Meeus de 1900 à
2150 (75 s pour le milieu de 2026, alors qu'on observe 69 s), et avec leur parabole à long terme en dehors : environ
35 minutes en 1000 et 74 en 3000. ΔT n'est pas connu si loin d'aujourd'hui : un instant à ces époques peut être décalé
de quelques minutes, ce qui reste bien moins qu'un jour.

## Lever, coucher et crépuscules

Un `Place` est une latitude, positive au nord, une longitude, positive à l'est, en degrés décimaux, et le fuseau horaire
dans lequel ses heures se lisent; une coordonnée hors de sa plage est une `InvalidPlace`. `SunTimes::on($place, $day)`
donne le `SolarDay` de la date où tombe `$day` dans ce fuseau horaire :

| Membre | Donne |
|---|---|
| `noon` | Le midi solaire, le passage du Soleil au méridien |
| `sunrise()`, `sunset()` | Le moment où le bord supérieur du Soleil franchit un horizon marin; null sous le Soleil de minuit ou dans la nuit polaire |
| `dawn(Twilight)`, `dusk(Twilight)` | Le début et la fin d'un crépuscule : civil par défaut, nautique, astronomique, soit le centre du Soleil à 6°, 12° ou 18° sous l'horizon |
| `sun`, `twilight(Twilight)` | Le `Crossing` de chaque hauteur : `morning`, `evening`, et `Altitude` (`Crosses`, `AlwaysAbove`, `AlwaysBelow`) |
| `dayLength()` | Les secondes du lever au coucher : 86 400 sous le Soleil de minuit, 0 dans la nuit polaire |

Le passage au méridien est le midi moyen à la longitude du lieu, corrigé par l'équation du temps. Chaque franchissement
part de lui : l'angle horaire où le centre du Soleil atteint la hauteur, pour la déclinaison du Soleil à cet instant,
donne un premier instant, où la déclinaison et l'équation du temps sont relues, quatre fois. Le lever et le coucher
placent le centre du Soleil à 0,8333° sous l'horizon (34′ de réfraction et 16′ de demi-diamètre du Soleil), comme les
tables publiées; l'altitude du lieu et le terrain autour ne sont pas comptés.

Un franchissement appartient au jour solaire qui entoure le midi de la date : à Reykjavík en juin, le coucher après
minuit est celui du soir, à la date suivante. Les heures sont à une minute près de celles qui sont publiées entre les
cercles polaires; près des jours où le Soleil ne fait qu'effleurer une hauteur, à quelques minutes près.

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

## Éclipses

`Eclipses::solar($from, $until)` et `Eclipses::lunar($from, $until)` donnent, dans l'ordre, les éclipses dont le maximum
tombe dans une période, fin exclue. Chaque nouvelle ou pleine lune proche d'un nœud de l'orbite de la Lune est corrigée
à l'instant du maximum de l'éclipse; son `gamma`, la plus courte distance de l'axe de l'ombre au centre de la Terre en
rayons terrestres, et le rayon `u` de l'ombre indiquent s'il y a une éclipse et de quel genre.

| Type | Membres |
|---|---|
| `SolarEclipse` | `at`; `kind`, un `SolarEclipseKind` (`Total`, `Annular`, `Hybrid`, `Partial`); `central`; `gamma`; `magnitude`, pour une éclipse partielle seulement |
| `LunarEclipse` | `at`; `kind`, un `LunarEclipseKind` (`Total`, `Partial`, `Penumbral`); `gamma`; `penumbralMagnitude`, `umbralMagnitude`; les durées `penumbral`, `partial` et `total`, chacune un début et une fin, null pour une phase que l'éclipse n'a pas |

La méthode trouve toutes les éclipses (224 éclipses solaires au XXIe siècle, dont 68 totales), donne le maximum à
quelques minutes près et les magnitudes à quelques centièmes près. Elle ne donne ni le trajet d'une éclipse solaire sur
la Terre, ni les lieux d'où une éclipse se voit : une éclipse lunaire se voit de partout où la Lune est levée, une
éclipse solaire d'une partie de la Terre seulement.

## Pâques

`Easter::western()` suit le comput grégorien (l'algorithme anonyme du chapitre 8 de Meeus). `Easter::orthodox()` suit
le comput julien, écrit dans le calendrier grégorien : 13 jours plus tard de 1900 à 2099. Les deux couvrent 1583 à
4099 et donnent minuit dans le fuseau horaire demandé.

## Le Nouvel An chinois

`ChineseNewYear::of()` suit les règles du calendrier de la Chine depuis 1645. Les jours sont à l'heure de la Chine; le
onzième mois contient le solstice de décembre; dans une année de treize mois, d'un onzième mois au suivant, le premier
mois sans terme principal (l'entrée du Soleil dans un signe) devient un mois intercalaire. Le nouvel an tombe deux
nouvelles lunes après le début du onzième mois, trois quand un onzième ou un douzième mois intercalaire s'intercale,
comme à la fin de 2033. `animal()` et `element()` suivent les cycles de douze et de dix : 2026 est l'année du cheval
de feu. Une année lit le solstice de décembre qui la précède et les nouvelles lunes après le suivant : `of()` donne les
années de 1001 à 2999, de `ChineseNewYear::FIRST_YEAR` à `LAST_YEAR`.

Les erreurs de plage sont des `OutOfRange`.
