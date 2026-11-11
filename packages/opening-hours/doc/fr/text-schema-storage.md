<!-- phil-dev source-sha256=d9a00ba103138c9c7bd0d9c671379d570a0bdff2df0453c311151237c96ad98c -->
# Texte, schema.org et stockage

## La forme texte

`HoursText::day()` lit un jour, ses plages séparées par des virgules, rien pour fermé. `HoursText::specials()` lit des
heures spéciales, une période par ligne — un jour ou `from..through`, ses plages et un libellé après `#` —, et
`specialsToText()` les réécrit :

```text
2026-12-24 09:00-12:00 # Christmas Eve
2026-12-25 # Christmas
2027-07-01..2027-08-31 10:00-16:00
```

Les lignes vides sont ignorées ; une ligne qui ne correspond à aucune de ces formes lance `InvalidOpeningHours`.

## schema.org

`OpeningHoursSchema::of($hours)` donne l'`openingHoursSpecification` d'un lieu :

```php
[
    ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday'], 'opens' => '09:00', 'closes' => '17:00'],
    ['@type' => 'OpeningHoursSpecification', 'opens' => '00:00', 'closes' => '00:00', 'validFrom' => '2026-12-25', 'validThrough' => '2026-12-25'],
]
```

Une plage de la semaine donne une spécification pour les jours qui la partagent. Chaque plage d'heures spéciales en
donne une avec sa période, et les heures spéciales qui ferment le lieu ouvrent et ferment à `00:00`, comme les moteurs
de recherche le lisent.

## Stockage

`OpeningHoursArray::from()` écrit les heures sous forme de tableau de textes et `to()` les relit. C'est ce que le type
de colonne de `phil/opening-hours-bundle` stocke en JSON :

```json
{"timezone": "America/Toronto",
 "week": {"1": "09:00-17:00", "6": "10:00-16:00"},
 "specials": [{"from": "2026-12-25", "through": "2026-12-25", "hours": "", "label": "Christmas"}]}
```

Les jours fermés sont omis de `week`. Tout ce qui diffère de ce qu'écrit `from()` lance `UnreadableOpeningHours`.
