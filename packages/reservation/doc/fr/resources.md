<!-- phil-dev source-sha256=10c065a438a6b2f847e780e5744c7ad477de652304b7068326d359569af8c6fb -->
# Ressources

Un `Bookable` est ce qui peut être réservé, tel que le voit la bibliothèque : pas de nom, pas d'identifiant, seulement
ce qui décide d'une réservation.

| Propriété | Par défaut | Sens |
|---|---|---|
| `capacity` | `1` | Les unités réservées en même temps : 1 pour une salle ou une personne, 8 pour les places d'une table, 12 pour un lot de chaises |
| `bufferBefore` | `0` | Minutes retenues avant chaque réservation : l'installation |
| `bufferAfter` | `0` | Minutes retenues après chaque réservation : le rangement, le nettoyage |
| `availability` | `AlwaysAvailable` | Quand elle peut être réservée, une `Availability` |
| `rules` | `[]` | Les `Rule` que suit chaque demande |
| `timezone` | UTC | L'horloge sur laquelle se lisent ses heures et sa grille |

Une capacité inférieure à un ou une marge négative lève `InvalidDefinition`.

## Marges

Une réservation retient ses unités de sa marge avant son début jusqu'à sa marge après sa fin. Les marges
s'additionnent : avec quinze minutes après et quinze avant, la réservation qui suit une réservation finissant à 10 h
commence au plus tôt à 10 h 30. Elles comptent dans la capacité comme la réservation elle-même.

## Heures

`AlwaysAvailable` ouvre la ressource à tout moment. `WeeklyAvailability` l'ouvre par jour de la semaine, selon sa
propre horloge :

```php
new WeeklyAvailability(
    [
        1 => [[540, 1020]],             // Monday, 9:00 to 17:00
        5 => [[540, 720], [780, 1440]], // Friday, 9:00 to 12:00 and 13:00 to midnight
        6 => [[0, 120]],                // Saturday, midnight to 2:00
    ],
    new DateTimeZone('America/Toronto'),
    closed: ['2026-12-25'],
);
```

Les jours de la semaine vont de 1 pour lundi à 7 pour dimanche, comme les numérote ISO 8601, et les intervalles d'une
minute de la journée à une minute plus tardive, jusqu'à 1440 pour minuit en fin de journée. Les intervalles d'une
journée ne se chevauchent pas ; des intervalles qui se touchent forment une seule plage, même de part et d'autre de
minuit : la soirée du vendredi ci-dessus se prolonge jusqu'à 2 h le samedi, et une réservation de 23 h à 1 h y entre.
Un jour fermé n'a pas d'heures, quel que soit son jour de la semaine. Le jour d'un changement d'heure, une heure qui
n'existe pas est lue comme l'heure qui suit le changement : la plage garde ses bornes murales et dure une heure de
moins ou de plus.

Les heures se lisent sur 1 000 jours au plus à la fois ; une période plus longue lève `InvalidInterval`.

## Règles

| Règle | Refuse |
|---|---|
| `MinimumNotice(minutes)` | Une réservation qui commence plus tôt après sa demande ; à zéro, une réservation dans le passé |
| `MaximumAdvance(minutes)` | Une réservation qui commence plus loin dans l'avenir : une fenêtre de réservation glissante |
| `DurationLimits(minimum, maximum)` | Une réservation plus courte que la durée minimale ou plus longue que la durée maximale, sans durée maximale par défaut |
| `SlotGrid(minutes)` | Une réservation qui ne commence pas sur un multiple du pas depuis minuit, ou qui ne dure pas un multiple du pas |

Le pas de la grille divise une journée : 15, 30, 60, 90, 120… L'application ajoute ses propres règles en implémentant
`Rule` : voir [Points d'extension](extension-points.md).
