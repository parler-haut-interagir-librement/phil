<!-- phil-dev source-sha256=63d6885bb1b71eabd4e40d40e834060c37f02a95599958bdf7342f9d694cb16b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Comment |
|---|---|
| À qui les places gardées sont offertes | Implémentez `Application\Contract\OfferPolicy` et faites-en un alias ; elle reçoit les places gardées et les entrées en attente par rang, et renvoie celles à qui offrir des places. Les priorités, les groupes d'amis ou les membres d'abord passent par là |
| Où les listes et les entrées sont conservées | Implémentez `Domain\Contract\WaitlistRepository` et `EntryRepository` et faites-en des alias ; `exclusively()` doit faire exécuter les requêtes d'une même liste une à la fois, la liste étant relue, et les lectures faites à l'intérieur doivent donner les entrées telles que stockées |
| Réagir à un changement | Écoutez `EntryJoined`, `PlaceOffered`, `OfferAccepted`, `OfferDeclined`, `OfferLapsed`, `EntryLeft` et `WaitlistDrawn` ; donnez les places sur `OfferAccepted` |
| Informer les participants | phil/notifications-bundle et les options `notifications` ; voyez [Notifications](notifications.md) |
| Les mots des refus | Les valeurs de `RefusalReason`, dans les traductions de l'application |
| Les règles propres à l'application | Vérifiez-les avant d'appeler `Waitlists::join()` : un compte en règle, un billet, un âge |
| Quand la liste s'ouvre, quand des places se libèrent | L'application appelle `open()`, `release()` et `reclaim()` : la liste ne compte jamais la capacité |
| La fenêtre d'offre, les places par entrée, ce que chaque requête lit | Les options `offer_window`, `max_places` et `batch` ; la fenêtre propre d'une liste à `open()` ou `changeOfferWindow()` |
| Faire expirer les offres | La commande `phil:waitlist:lapse`, ou `Waitlists::lapseExpired()`, selon l'horaire de l'application |
| L'horloge | Le service `clock` : le bundle ne lit jamais l'heure lui-même |

La politique est appelée sous le verrou de la liste, dans sa transaction : elle décide à partir de ce qu'on lui donne
et ne lit rien d'autre. Son choix est vérifié ; un choix qui prend plus de places qu'il n'y en a de gardées, ou une
entrée qu'on ne lui a pas donnée, annule le changement avec une `UnexpectedValueException`.
