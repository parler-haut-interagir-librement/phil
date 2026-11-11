<!-- phil-dev source-sha256=e76d823da6382c0df84cd049d8c5bf22a1faee8343fac198fbff535f12687efd -->
# Créneaux libres

```php
$slots = $bookable->slots($period, minutes: 60, bookings: $bookings, now: $now, quantity: 1, step: 30, limit: 100);
```

`slots()` donne, dans l'ordre, les créneaux de la période qu'obtiendrait une demande faite à `$now` : dans l'une des
plages ouvertes de la ressource et dans la période, en suivant chaque règle, avec assez d'unités libres, marges
comprises. Chaque créneau est un `Interval` sur l'horloge de la ressource.

| Argument | Sens |
|---|---|
| `$minutes` | La durée d'un créneau |
| `$quantity` | Les unités que chaque créneau doit avoir de libres ; hors de 1 à la capacité, aucun créneau |
| `$step` | Les minutes entre deux débuts, la durée d'un créneau par défaut |
| `$limit` | Le plus grand nombre de créneaux donnés, de 1 à `Bookable::MAX_SLOTS` (1 000) |

Les débuts avancent selon le pas depuis minuit sur l'horloge de la ressource, et repartent à chaque minuit : avec un
pas de 30, une période qui ouvre à 9 h 10 offre d'abord 9 h 30 ; avec un pas de 7 heures, 0 h, 7 h, 14 h, 21 h, puis
de nouveau 0 h. Un pas qui divise une journée garde une même grille d'un jour à l'autre.

Le parcours lit au plus `Bookable::MAX_CANDIDATES` (10 000) débuts par appel et renvoie ce qu'il a trouvé : une
ressource entièrement réservée sur une longue période coûte un temps borné. Une durée, un pas ou une limite hors des
bornes lève `InvalidDefinition`.

## Unités libres

```php
$bookable->free($interval, $bookings);          // 0 to the capacity
$bookable->free($interval, $bookings, 'b-1');   // as if b-1 were moved away
```

`free()` donne les unités encore libres sur tout l'intervalle, marges comprises : ce qu'une page montre à côté d'un
créneau, ou le plus grand nombre de places qu'offre un formulaire.
