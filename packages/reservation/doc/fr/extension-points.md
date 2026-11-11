<!-- phil-dev source-sha256=8826624a5a35acd04aa09c9af60582cd0eaeaa5c0815e90b477eace622d69c4a -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service, et ses classes sont finales. Ce que l'application
choisit passe par deux ports et par des arguments.

| Point | Comment |
|---|---|
| Quand une ressource est ouverte | Implémentez `Phil\Reservation\Contract\Availability` : les plages ouvertes qui croisent une période, dans l'ordre, celles qui se touchent réunies ; jours fériés, heures d'ouverture conservées ailleurs, horaire partagé |
| Ce qu'une réservation doit suivre | Implémentez `Phil\Reservation\Contract\Rule` : le quota d'un titulaire, un rôle, un prix ; elle renvoie une `Violation` ou `null` |
| Ce par rapport à quoi une réservation est vérifiée | Les `Booking` passés à `check()`, `free()` et `slots()` : l'application décide lesquels comptent, les réservations confirmées et les retenues non expirées |
| Déplacer une réservation | L'identifiant `$replacing` de `BookingRequest` et de `free()` |
| L'horloge | Le `$requestedAt` de `BookingRequest` et le `$now` de `slots()` et de `ReminderSchedule::due()` : la bibliothèque ne lit jamais l'heure elle-même |
| Les messages, dans toute langue | Écrits par l'application à partir des valeurs de `ViolationCode` et des paramètres de chaque violation ; la bibliothèque n'en écrit aucun |
| Le nombre de créneaux lus | La `$limit` de `slots()`, dans la limite de `Bookable::MAX_SLOTS` |
| L'interception des erreurs de la bibliothèque | Chaque exception implémente `Phil\Reservation\Exception\ReservationException` |

Une règle propre à l'application reçoit la demande et le fuseau horaire de la ressource, et ne garde aucun état entre
les appels : un seul `Bookable` sert ainsi chaque requête d'un *worker* de longue durée.
