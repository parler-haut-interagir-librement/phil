<!-- phil-dev source-sha256=cb278a474bc895f8b811f6d782ee9f3b1e1ef1b081cde9d1c80aac2380b44e56 -->
# Dates ajoutées et retirées

`RecurrenceDates::parse()` lit une ligne de dates telle que la RFC 5545 l'écrit à côté d'une règle : `RDATE`, les dates
ajoutées aux occurrences, ou `EXDATE`, celles qu'on retire. `(string) $dates` la réécrit.

```php
$added = RecurrenceDates::parse('RDATE;TZID=America/Toronto:20261224T190000,20261231T190000');
$removed = RecurrenceDates::parse('EXDATE;VALUE=DATE:20261225', new DateTimeZone('America/Toronto'));

$recurrence = Recurrence::of($start, $rule, exclusions: $removed->dates, additions: $added->dates);

(string) $added;   // "RDATE;TZID=America/Toronto:20261224T190000,20261231T190000"
```

| Forme | Lue comme |
|---|---|
| `RDATE;VALUE=DATE:20261224,20261226` | Des jours entiers, à minuit dans le fuseau de `TZID`, ou dans le fuseau donné à `parse()` |
| `RDATE:20261224T000000Z` | Des heures en UTC |
| `RDATE;TZID=America/Toronto:20261224T190000` | Des heures dans un fuseau que PHP connaît par son nom IANA, ou un décalage, entre guillemets ou non |
| `RDATE:20261224T190000` | Des heures flottantes, lues dans le fuseau donné à `parse()`, ou dans le fuseau par défaut de PHP |

Le nom de la propriété et les paramètres sont lus dans toutes les casses, et une ligne repliée est d'abord dépliée. Une
heure qu'un changement d'heure saute, comme 2 h 30 la nuit où l'on avance l'heure, devient l'heure qui suit, comme le
dit la RFC 5545.

| Propriété | Contient |
|---|---|
| `property` | `RDATE` ou `EXDATE` |
| `dates` | Les dates, en `DateTimeImmutable`, dans l'ordre de la ligne |
| `isDate` | Si ce sont des jours entiers |
| `timezone` | Le fuseau où elles s'écrivent : celui de `TZID`, celui où les heures flottantes ont été lues, ou `null` pour UTC |

`RecurrenceDates::of($property, $dates, $isDate = false, $timezone = null)` en construit une : les jours entiers
s'écrivent comme des jours, les heures dans leur fuseau avec `TZID`, ou en UTC sans fuseau. Un fuseau dont le nom
contient `:`, `;` ou `,`, comme le décalage `+05:30`, est mis entre guillemets. Une ligne lue puis réécrite se relit à
l'identique.

## Ce qui est refusé

`InvalidRule` nomme ce qu'il refuse : une autre propriété que `RDATE` ou `EXDATE` ; un autre paramètre que `VALUE` et
`TZID`, ou un paramètre donné deux fois ; les périodes (`VALUE=PERIOD`), qui ne sont pas lues ; un fuseau que PHP ne
connaît pas, ou qui contient un caractère hors des lettres, des chiffres, de `_+-/:.` et de l'espace, ou qui dépasse
64 octets ; une heure parmi des jours ou un jour parmi des heures ; une date qui n'existe pas, ou hors des années 1
à 9999.

**Une ligne coûte un temps borné** : au plus `RecurrenceDates::MAX_DATES` dates, 1 000, et
`RecurrenceDates::MAX_LENGTH` octets, 17 500, sont lus ; une ligne plus longue est refusée avant d'être lue.
