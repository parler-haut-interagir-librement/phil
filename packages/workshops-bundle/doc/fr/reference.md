<!-- phil-dev source-sha256=2ac38f1b27d8a0fb6779d65b5e75d1ce363512d1360b4a77ca2b714756aaf20a -->
# Référence

| Type | Rôle |
|---|---|
| `PhilWorkshopsBundle` | Branche les services, associe les entités, configure `phil_workshops`, et ajoute les listes d'attente quand phil/waitlist-bundle est activé |
| `Domain\Model\Workshop` | Ce qui est enseigné : son titre, et s'il prend de nouvelles séances |
| `Domain\Model\WorkshopSession` | Une occasion où un atelier est donné : plage, fuseau horaire, capacité, fenêtre d'inscription, statut |
| `Domain\Model\Registration` | Les places d'un participant dans une séance |
| `Domain\Status\SessionStatus` | Planifiée, annulée |
| `Domain\Status\RegistrationStatus` | Confirmée, annulée |
| `Domain\Status\RefusalReason` | Pourquoi une séance refuse une inscription ou une place sur sa liste d'attente |
| `Domain\Contract\WorkshopRepository` | Le port des ateliers |
| `Domain\Contract\SessionRepository` | Le port des séances |
| `Domain\Contract\RegistrationRepository` | Le port des inscriptions et du verrou de la séance |
| `Domain\Event\Registration*`, `Domain\Event\Session*` | Les événements des changements, avec des identifiants seulement |
| `Domain\Exception\InvalidWorkshop` | Une valeur ou un changement refusé |
| `Application\Workshops` | Crée des ateliers ; planifie, déplace, redimensionne et annule des séances ; inscrit et annule ; compte les places |
| `Application\SeatCount` | Les places d'une séance : capacité, prises, libres, réclamées, disponibles |
| `Application\Contract\SeatClaims` | Le port qui dit quelles places libres une liste d'attente réclame, et combien elle en a offert |
| `Application\Exception\RegistrationRefused` | Un refus, avec sa raison et ses paramètres |
| `Application\Exception\NotFound` | Un atelier, une séance ou une inscription introuvable |
| `Infrastructure\Doctrine\DoctrineWorkshopRepository` | Les ateliers avec Doctrine |
| `Infrastructure\Doctrine\DoctrineSessionRepository` | Les séances avec Doctrine |
| `Infrastructure\Doctrine\DoctrineRegistrationRepository` | Les inscriptions et le verrou avec Doctrine |
| `Infrastructure\Translation\RefusalMessages` | Les refus et les statuts dans la langue du lecteur |
| `Integration\Waitlist\SessionWaitlists` | La liste d'attente de chaque séance dans phil/waitlist-bundle : joindre, accepter une offre, équilibrer, fermer |
| `Integration\Waitlist\WaitlistSeatClaims` | `SeatClaims` lu depuis la liste d'attente de la séance |
| `Integration\Waitlist\SessionWaitlistListener` | Équilibre la liste d'une séance quand des places se libèrent ou qu'une inscription la quitte, la ferme quand la séance est annulée |

| Service | Public | Arguments |
|---|---|---|
| `Application\Workshops` | oui | Les trois ports, `clock`, `event_dispatcher` s'il existe, `max_seats`, `SeatClaims` s'il y en a un |
| `Infrastructure\Translation\RefusalMessages` | oui | `translator` |
| `Domain\Contract\*Repository` | oui, alias | Les adaptateurs Doctrine, sur `doctrine.orm.entity_manager` |
| `Integration\Waitlist\SessionWaitlists` | oui, avec les listes d'attente | `SessionRepository`, `RegistrationRepository`, `Waitlists`, `clock`, `event_dispatcher` s'il existe, `max_seats` |
| `Application\Contract\SeatClaims` | alias, avec les listes d'attente | `WaitlistSeatClaims`, sur `Waitlists` |
| `Integration\Waitlist\SessionWaitlistListener` | non, avec les listes d'attente | `SessionWaitlists` ; écoute `RegistrationCancelled`, `SessionResized`, `SessionCancelled` et `EntryLeft` de phil/waitlist-bundle |

Les services marqués « avec les listes d'attente » existent quand phil/waitlist-bundle est installé et activé et que
l'option `waitlist` est active.
