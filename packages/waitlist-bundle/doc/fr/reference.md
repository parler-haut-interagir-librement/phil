<!-- phil-dev source-sha256=59af83cbeeef90688db243b1e1868bf59f580f1878b09b232fad27a667579096 -->
# Référence

| Type | Rôle |
|---|---|
| `PhilWaitlistBundle` | Câble les services, mappe les entités, configure `phil_waitlist`, retire le notificateur sans le bundle de notifications |
| `Domain\Model\Waitlist` | La liste d'un sujet : son mode, sa fenêtre d'offre, les places gardées, fermée ou non, son tirage |
| `Domain\Model\Entry` | La place d'un participant dans une liste, son rang, son offre et son échéance |
| `Domain\Status\EntryStatus` | En attente, offerte, acceptée, refusée, expirée, quittée |
| `Domain\Status\WaitlistMode` | File, tirage |
| `Domain\Contract\WaitlistRepository` | Le port des listes et du verrou de liste |
| `Domain\Contract\EntryRepository` | Le port des entrées |
| `Domain\Event\EntryJoined`, `PlaceOffered`, `OfferAccepted`, `OfferDeclined`, `OfferLapsed`, `EntryLeft`, `WaitlistDrawn` | Les événements des changements, avec des identifiants seulement |
| `Domain\Exception\InvalidWaitlist` | Une valeur ou un changement refusé |
| `Application\Waitlists` | Ouvre des listes ; inscrit, remet, offre, accepte, refuse, quitte, fait expirer, tire, reprend, ferme ; donne les positions et les décomptes |
| `Application\Standing` | Où en est une entrée : état, position, échéance |
| `Application\ListCount` | Les places d'une liste : en attente, offertes, gardées |
| `Application\Contract\OfferPolicy` | Le port qui choisit les entrées en attente à qui offrir des places |
| `Application\Policy\FirstInLine` | La politique par défaut : strictement par rang |
| `Application\Policy\FirstThatFits` | Par rang, en passant par-dessus les entrées qui en demandent davantage |
| `Application\Exception\WaitlistRefused` | Un refus, avec sa raison et ses paramètres |
| `Application\Exception\RefusalReason` | Fermée, trop de places, aucune offre, offre expirée |
| `Application\Exception\NotFound` | Une liste ou une entrée absente |
| `Infrastructure\Doctrine\DoctrineWaitlistRepository` | Les listes et le verrou avec Doctrine |
| `Infrastructure\Doctrine\DoctrineEntryRepository` | Les entrées avec Doctrine |
| `Infrastructure\Console\LapseOffersCommand` | `phil:waitlist:lapse` |
| `Integration\Notifications\WaitlistNotifier` | Les notifications aux participants, avec phil/notifications-bundle |

| Service | Public | Arguments |
|---|---|---|
| `Application\Waitlists` | oui | Les deux ports, `OfferPolicy`, `clock`, `event_dispatcher` s'il y en a un, `offer_window`, `max_places`, `batch` |
| `Domain\Contract\WaitlistRepository`, `EntryRepository` | oui, alias | Les adaptateurs Doctrine sur `doctrine.orm.entity_manager` |
| `Application\Contract\OfferPolicy` | non, alias | `FirstInLine` ou `FirstThatFits`, selon `policy` |
| `Infrastructure\Console\LapseOffersCommand` | non | `Waitlists` ; étiquette `console.command` |
| `Integration\Notifications\WaitlistNotifier` | non | `Notifications`, `NotificationTypes`, `EntryRepository`, les types par événement ; un écouteur des événements qui ont un type |

| Table | Contenu |
|---|---|
| `phil_waitlist` | Les listes, une par sujet |
| `phil_waitlist_entry` | Les entrées, indexées par liste, état et rang, par liste et participant, et par état et échéance |
