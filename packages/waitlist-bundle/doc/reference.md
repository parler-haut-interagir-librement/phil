# Reference

| Type | Role |
|---|---|
| `PhilWaitlistBundle` | Wires the services, maps the entities, configures `phil_waitlist`, removes the notifier without the notifications bundle |
| `Domain\Model\Waitlist` | The list of one subject: its mode, its offer window, the places held, closed or not, its draw |
| `Domain\Model\Entry` | A participant's place in a list, its rank, its offer and its deadline |
| `Domain\Status\EntryStatus` | Waiting, offered, accepted, declined, lapsed, left |
| `Domain\Status\WaitlistMode` | Queue, draw |
| `Domain\Contract\WaitlistRepository` | The port of the lists and of the list's lock |
| `Domain\Contract\EntryRepository` | The port of the entries |
| `Domain\Event\EntryJoined`, `PlaceOffered`, `OfferAccepted`, `OfferDeclined`, `OfferLapsed`, `EntryLeft`, `WaitlistDrawn` | The events of the changes, with identifiers only |
| `Domain\Exception\InvalidWaitlist` | A value or a change refused |
| `Application\Waitlists` | Opens lists; joins, releases, offers, accepts, declines, leaves, lapses, draws, reclaims, closes; gives positions and counts |
| `Application\Standing` | Where an entry stands: status, position, deadline |
| `Application\ListCount` | A list's places: waiting, offered, held |
| `Application\Contract\OfferPolicy` | The port that picks the waiting entries to offer places to |
| `Application\Policy\FirstInLine` | The default policy: strictly by rank |
| `Application\Policy\FirstThatFits` | By rank, passing over the entries that need more |
| `Application\Exception\WaitlistRefused` | A refusal, with its reason and its parameters |
| `Application\Exception\RefusalReason` | Closed, too many places, no offer, offer expired |
| `Application\Exception\NotFound` | A list or an entry missing |
| `Infrastructure\Doctrine\DoctrineWaitlistRepository` | The lists and the lock with Doctrine |
| `Infrastructure\Doctrine\DoctrineEntryRepository` | The entries with Doctrine |
| `Infrastructure\Console\LapseOffersCommand` | `phil:waitlist:lapse` |
| `Integration\Notifications\WaitlistNotifier` | The notifications of the participants, with phil/notifications-bundle |

| Service | Public | Arguments |
|---|---|---|
| `Application\Waitlists` | yes | The two ports, `OfferPolicy`, `clock`, `event_dispatcher` if any, `offer_window`, `max_places`, `batch` |
| `Domain\Contract\WaitlistRepository`, `EntryRepository` | yes, aliases | The Doctrine adapters on `doctrine.orm.entity_manager` |
| `Application\Contract\OfferPolicy` | no, alias | `FirstInLine` or `FirstThatFits`, by `policy` |
| `Infrastructure\Console\LapseOffersCommand` | no | `Waitlists`; tag `console.command` |
| `Integration\Notifications\WaitlistNotifier` | no | `Notifications`, `NotificationTypes`, `EntryRepository`, the types by event; a listener of the events with a type |

| Table | Holds |
|---|---|
| `phil_waitlist` | The lists, one per subject |
| `phil_waitlist_entry` | The entries, indexed by list, status and rank, by list and participant, and by status and deadline |
