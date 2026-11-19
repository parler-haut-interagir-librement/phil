<!-- phil-dev source-sha256=0fc37b23dd2ec022f32132e566c7c8b0caf92b38aae136f6bb322ecd8230f29c -->
# Points d'extension

| Point | Comment |
|---|---|
| L'endroit où les ressources et les réservations sont conservées | Implémentez `Domain\Contract\ResourceRepository` et `Domain\Contract\ReservationRepository` et créez-en des alias ; `exclusively()` doit faire s'exécuter une à la fois les requêtes sur une même ressource |
| La façon d'envoyer les rappels | Implémentez `Application\Contract\ReminderSender` et créez-en un alias, ou traitez le message `SendReservationReminder` |
| Réagir à un changement | Écoutez `ReservationHeld`, `ReservationConfirmed`, `ReservationRescheduled`, `ReservationCancelled` et `ReservationExpired` |
| Les mots des raisons et les libellés | Les messages du domaine `phil_reservation`, dans les traductions de l'application |
| Des règles propres à l'application | Vérifiez-les avant d'appeler `Reservations`, ou construisez un `Bookable` de `phil/reservation` avec votre propre `Rule` |
| La quantité que lit chaque exécution | L'option `batch` |
| Le calendrier | L'option `calendar`, et les étiquettes des éléments pour les filtres du calendrier |
| L'horloge | Le service `clock` : le bundle ne lit jamais l'heure lui-même |

## Ce qui est construit sur le bundle

Le bundle est le cœur des réservations qu'offre une application ; trois usages sont prévus comme paquets à part, chacun
par-dessus lui sans le modifier :

| Usage | Ce qu'il ajoute | Sur |
|---|---|---|
| Des rendez-vous avec une personne ou un service | Des services et leurs durées, les prestataires qui les donnent, « n'importe quel prestataire », une page de réservation | Une `BookableResource` par prestataire, de capacité un ; `slots()` pour les heures libres ; `ReservationConfirmed` pour la confirmation |
| Location d'équipement | Des articles prêtés et rendus, des accessoires réservés avec une salle | Une ressource par sorte d'article, sa capacité étant le nombre d'articles ; `quantity` pour plusieurs ; les temps tampons pour le nettoyage ou la vérification entre deux prêts |
| Hébergement | Des nuits au lieu d'heures, des arrivées et des départs à heures fixes | Des ressources de journées entières, la grille des créneaux et les limites de durée pour les nuits ; les heures pour les heures d'arrivée et de départ |

Chacun garde dans ses propres tables ce qu'il ajoute — un service, les compétences d'un prestataire, l'état d'un
article — par les identifiants des ressources et des réservations, et réserve par `Reservations`, pour que le verrou et
les règles restent ceux du bundle. Le titulaire d'une réservation est un identifiant que choisit l'application, jamais
une adresse courriel : voyez [Réservations](reservations.md#moments-et-titulaires).
