<!-- phil-dev source-sha256=0e7cf1a612980d0eab9fe43f81d69067d72b9a881c0e74ebabe501b3acfb5418 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Comment |
|---|---|
| Une liste d'attente par séance | Installez et activez phil/waitlist-bundle : `SessionWaitlists` joint ses listes et accepte leurs offres, et l'écouteur les garde en phase avec les places |
| Qui reçoit l'offre des places libérées, et pour combien de temps | Les options de phil/waitlist-bundle et son port `OfferPolicy` : `first_in_line`, `first_that_fits`, ou une politique propre à l'application ; une fenêtre d'offre par liste |
| Les places libres qu'une nouvelle inscription ne prend pas | Implémentez `Application\Contract\SeatClaims` et créez-lui un alias, pour une liste d'attente gardée ailleurs ; elle est interrogée sous le verrou de la séance, dans sa transaction |
| Où sont gardés les ateliers, les séances et les inscriptions | Implémentez `Domain\Contract\WorkshopRepository`, `SessionRepository` et `RegistrationRepository` et créez-leur des alias ; `exclusively()` doit faire exécuter une à une les requêtes sur une même séance, la séance étant relue |
| Réagir à un changement | Écoutez `RegistrationConfirmed`, `RegistrationCancelled`, `SessionResized`, `SessionRescheduled` et `SessionCancelled`, et les événements de phil/waitlist-bundle pour les inscriptions en attente ; envoyez-y les courriels, les notifications et les reçus |
| Les mots des refus et des statuts | Les messages du domaine `phil_workshops`, dans les traductions de l'application |
| Les règles propres à l'application | Vérifiez-les avant d'appeler `Workshops::register()` ou `SessionWaitlists::join()` : un âge, un niveau, un paiement, un billet |
| Ce qu'un atelier ou une séance ajoute | Les tables propres à l'application, par les identifiants de l'atelier et de la séance |
| Les places par inscription, la liste d'attente activée ou non | Les options `max_seats` et `waitlist` |
| L'horloge | Le service `clock` : le bundle ne lit jamais l'heure lui-même |

`SeatClaims` décide d'après ce que contient la liste d'attente et ne lit rien des inscriptions de la séance :
`Workshops` compte lui-même les places prises, et donne à une nouvelle inscription les places libres moins celles
réclamées.
