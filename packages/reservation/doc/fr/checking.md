<!-- phil-dev source-sha256=7811d15b97acba706ab4557ac2d2ec46eff92f6160f8c6c4ad86ca69e4e37f79 -->
# Vérifier une demande

```php
$decision = $bookable->check(new BookingRequest($interval, $now, quantity: 2, replacing: null), $bookings);
```

`BookingRequest` contient la plage demandée, le moment de la demande — le préavis et la fenêtre de réservation se
comptent à partir de lui —, les unités demandées, et l'identifiant de la réservation qu'elle remplace quand une
réservation existante est déplacée ou redimensionnée : cette réservation est écartée des conflits. `$bookings` sont les
`Booking` qui retiennent des unités : l'application passe les réservations confirmées et les retenues non expirées, au
moins celles de la période.

## La décision

`Decision::allowed()` est vrai quand rien ne refuse la demande. Sinon, `violations` liste chaque raison, dans cet
ordre : la quantité, les règles dans leur ordre, les heures, la capacité. Chaque `Violation` porte un `ViolationCode`
dont la valeur de chaîne est stable, pour la conserver et la traduire, et les nombres dont son message a besoin :

| Code | Valeur | Paramètres |
|---|---|---|
| `Quantity` | `quantity` | `max` : la capacité |
| `TooSoon` | `too_soon` | `minutes` : le préavis |
| `TooFarAhead` | `too_far_ahead` | `minutes` : la fenêtre de réservation |
| `TooShort` | `too_short` | `minutes` : la durée minimale |
| `TooLong` | `too_long` | `minutes` : la durée maximale |
| `OffGrid` | `off_grid` | `minutes` : le pas |
| `Unavailable` | `unavailable` | aucun |
| `Full` | `full` | `free` : les unités libres sur toute la plage |

Une quantité inférieure à un ou supérieure à la capacité est refusée avec `Quantity` seulement, sans `Full`.

## Les conflits

`Decision::conflicts` liste les identifiants des réservations dont la plage, marges comprises, croise celle de la
demande, que la demande soit refusée ou non : une table de huit dont trois places sont prises en accepte quatre de plus
et nomme quand même la réservation avec laquelle elle partage la soirée. L'application les montre, ou demande avant de
les déplacer.

## La capacité

Les unités retenues à chaque moment viennent des plages des réservations, disposées une seule fois par `LoadProfile`.
La demande est complète quand le plus grand nombre d'unités retenues en même temps sur sa plage, marges comprises,
en laisse moins qu'elle n'en demande. Deux réservations qui ne font que se toucher ne retiennent jamais d'unités au
même moment.

## Vérifier et enregistrer

`check()` ne fait que lire les réservations qu'on lui passe : il ne les retient pas pendant que l'application enregistre
la nouvelle. Deux demandes pour la dernière unité libre au même instant peuvent être acceptées toutes les deux, et
enregistrées toutes les deux, au-delà de la capacité. L'application sérialise la vérification et l'écriture pour une
même ressource : un verrou sur elle (Symfony Lock, ou `SELECT … FOR UPDATE` sur sa ligne) tenu pendant que les
réservations sont chargées, vérifiées et la réservation écrite ; ou une contrainte dans le stockage qui refuse un
chevauchement au-delà de la capacité, une écriture échouée étant alors traitée comme `Full`.
