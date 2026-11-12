<!-- phil-dev source-sha256=62b2f51e999aaafbddb3972ec61c127420ceb1a5be1b5d32ac6877bb8f14421c -->
# L'almanach

## Les définitions

Une `Definition` est une date que l'almanach calcule :

| Argument | | |
|---|---|---|
| `key` | obligatoire | Une lettre minuscule ou un chiffre, puis jusqu'à 99 de ces caractères, `.`, `-` ou `_`; une date par clé |
| `title` | obligatoire | Son nom |
| `rule` | obligatoire | Son début |
| `until` | aucune | Sa fin : la première date de cette règle le jour du début ou après, cette année-là ou la suivante |
| `days` | `1` | Le nombre de jours qu'elle dure, de 1 à 366, quand il n'y a pas de `until` |
| `allDay` | `true` | Des jours entiers; `false` seulement pour une règle du ciel, montrée à son instant |
| `tags` | `[]` | Les mots sur lesquels l'almanach filtre |

```php
new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03'));
new Definition('engineers-week', 'Engineers week', RuleParser::parse('monday on or before 02-22'), days: 7);
new Definition('full-moon', 'Full moon', RuleParser::parse('full moon'), allDay: false, tags: ['moon']);
```

`spans(year, zone)` donne ses `DateSpan` qui commencent dans une année : `start` et `end` (les minuits du premier et
du dernier jour, ou le même instant), `allDay`, `days()` et `overlaps(from, until)`. Un `until` qui ne survient ni dans
l'année ni dans la suivante termine la durée à son premier jour.

## Les dates d'une période

```php
$almanac = new Almanac($definitions, new DateTimeZone('America/Toronto'));
$almanac->between($from, $until);           // every ComputedDate that touches the period
$almanac->between($from, $until, ['moon']); // those with one of the tags
```

- **Une période inclut son début et exclut sa fin**, et ses bornes sont des instants. Dans le fuseau horaire par
  défaut de PHP, `new DateTimeImmutable('2026-09-01')` est un autre moment que le minuit de l'almanach quand les deux
  fuseaux diffèrent. Donnez les bornes dans le fuseau horaire de l'almanach pour obtenir ses jours.
- **Les années autour de la période sont lues aussi** : une date qu'un décalage fait entrer dans la période depuis
  l'année suivante (`01-06 - 12 days`) ou depuis deux ans plus tôt, ou une durée qui a commencé alors. Quand une règle
  n'a pas de date pour une telle année (Pâques avant 1583), elle est omise. Une année de la période elle-même hors de
  la plage d'une règle produit un `OutOfRange`.
- **Une règle de la bibliothèque lit seulement les années dont elle a besoin** : ses dates tombent au plus à une
  semaine de leur année (`saturday after 12-31`). Une période lit donc les années dont les dates, déplacées par leur
  décalage et étalées sur leurs jours, peuvent la toucher : un mois de `12-25` lit une année, pas quatre. Une telle
  règle implémente `Rule\WithinTheYear`. Une règle d'application qui ne l'implémente pas est lue sur les deux années
  avant la période et sur celle d'après.
- **Une durée commencée avant la période et toujours en cours en fait partie** : le soir du 31 décembre amène une date
  qui a commencé la veille.
- **Les dates arrivent par début, puis par titre.** Chacune est une `ComputedDate` : sa `definition` et sa `span`.

Rien n'est stocké : calculer une année de dates prend bien moins d'une milliseconde par définition. Le Nouvel An
chinois d'une année, qui lit plus d'une année de nouvelles lunes, est calculé une fois et gardé pour la durée du
processus.
